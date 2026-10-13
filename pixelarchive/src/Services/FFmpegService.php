<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;

/**
 * Thin, safe wrapper around ffmpeg / ffprobe.
 * Every shell argument goes through escapeshellarg(). Numeric inputs are validated.
 */
final class FFmpegService
{
    /** @throws \RuntimeException when probing fails */
    public function probe(string $absPath): array
    {
        if (!is_file($absPath)) {
            throw new \RuntimeException('File not found for probing.');
        }
        $ffprobe = escapeshellarg(Config::getString('ffprobe', 'ffprobe'));
        $file = escapeshellarg($absPath);
        $cmd = "{$ffprobe} -v error -print_format json "
             . "-show_format -show_streams {$file} 2>&1";

        $out = [];
        $code = 0;
        exec($cmd, $out, $code);
        $raw = implode("\n", $out);
        if ($code !== 0) {
            throw new \RuntimeException('ffprobe failed: ' . substr($raw, 0, 300));
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new \RuntimeException('ffprobe returned unparseable output.');
        }

        $duration = isset($json['format']['duration']) ? (float) $json['format']['duration'] : 0.0;
        $width = 0;
        $height = 0;
        foreach ($json['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $width = (int) ($stream['width'] ?? 0);
                $height = (int) ($stream['height'] ?? 0);
                if ($duration <= 0) {
                    $duration = (float) ($stream['duration'] ?? 0);
                }
                break;
            }
        }

        return ['duration' => round($duration, 3), 'width' => $width, 'height' => $height];
    }

    /**
     * Extract a letterboxed thumbnail at Config frameTime seconds.
     * Returns the absolute thumbnail path, or null on failure (caller falls back to placeholder).
     */
    public function extractThumbnail(string $absVideoPath, string $absThumbPath): ?string
    {
        $frameTime = max(0, (float) Config::getInt('frameTime', 5));
        $w = max(16, Config::getInt('thumbWidth', 640));
        $h = max(16, Config::getInt('thumbHeight', 360));

        // pad to target aspect instead of distorting.
        $filter = sprintf(
            "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:color=black",
            $w, $h, $w, $h
        );

        $ffmpeg = escapeshellarg(Config::getString('ffmpeg', 'ffmpeg'));
        $in = escapeshellarg($absVideoPath);
        $out = escapeshellarg($absThumbPath);
        $ss = escapeshellarg(number_format($frameTime, 3, '.', ''));
        $vf = escapeshellarg($filter);

        $cmd = "{$ffmpeg} -y -ss {$ss} -i {$in} -vf {$vf} "
             . "-frames:v 1 -q:v 3 {$out} 2>&1";

        @mkdir(dirname($absThumbPath), 0775, true);
        $res = [];
        $code = 0;
        exec($cmd, $res, $code);

        if ($code === 0 && is_file($absThumbPath) && filesize($absThumbPath) > 0) {
            return $absThumbPath;
        }

        // Retry from the very beginning — short clips may have no frame at offset.
        $cmd2 = "{$ffmpeg} -y -i {$in} -vf {$vf} -frames:v 1 -q:v 3 {$out} 2>&1";
        exec($cmd2, $res, $code2);
        if ($code2 === 0 && is_file($absThumbPath) && filesize($absThumbPath) > 0) {
            return $absThumbPath;
        }

        @unlink($absThumbPath);
        error_log('[PixelArchive] Thumbnail extraction failed: ' . substr(implode("\n", $res), 0, 400));
        return null;
    }

    /**
     * Remove a section [start,end] from a video by concatenating the kept parts.
     * Writes to temp first; replaces the original only on success.
     *
     * @throws \RuntimeException with a readable message on failure.
     */
    public function removeSection(string $absVideoPath, float $start, float $end): void
    {
        if (!is_finite($start) || !is_finite($end) || $start < 0 || $end <= $start) {
            throw new \RuntimeException('Invalid trim range: start must be less than end and both non-negative.');
        }

        $info = $this->probe($absVideoPath);
        $duration = $info['duration'];
        if ($duration <= 0) {
            throw new \RuntimeException('Could not determine video duration.');
        }
        if ($end >= $duration) {
            throw new \RuntimeException(sprintf(
                'Trim end (%s s) exceeds video duration (%s s).',
                number_format($end, 2),
                number_format($duration, 2)
            ));
        }

        $ext = strtolower(pathinfo($absVideoPath, PATHINFO_EXTENSION)) ?: 'mp4';
        $tmp = rtrim(Config::storagePath('temp'), '/\\') . DIRECTORY_SEPARATOR
             . 'trim-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!is_dir(dirname($tmp))) {
            @mkdir(dirname($tmp), 0775, true);
        }

        $ss1 = number_format($start, 3, '.', '');
        $dur1 = number_format($duration - $end, 3, '.', '');
        $filter = sprintf(
            "[0:v]trim=start=0:end=%s,setpts=PTS-STARTPTS[v1];"
            . "[0:a]atrim=start=%s:duration=%s,asetpts=PTS-STARTPTS[a1];"
            . "[0:v]trim=start=%s,setpts=PTS-STARTPTS[v2];"
            . "[0:a]atrim=start=0:duration=%s,asetpts=PTS-STARTPTS[a2];"
            . "[v1][a1][v2][a2]concat=n=2:v=1:a=1[outv][outa]",
            $ss1, $ss1, $dur1, $ss1, $ss1
        );

        $ffmpeg = escapeshellarg(Config::getString('ffmpeg', 'ffmpeg'));
        $in = escapeshellarg($absVideoPath);
        $out = escapeshellarg($tmp);
        $fc = escapeshellarg($filter);

        $cmd = "{$ffmpeg} -y -i {$in} -filter_complex {$fc} "
             . "-map [outv] -map [outa] -c:v libx264 -preset fast -crf 20 -c:a aac {$out} 2>&1";

        $res = [];
        $code = 0;
        exec($cmd, $res, $code);

        if ($code !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            // Video-only fallback for files without an audio stream.
            $filterV = sprintf(
                "[0:v]split=2[va][vb];[va]trim=start=0:end=%s,setpts=PTS-STARTPTS[v1];"
                . "[vb]trim=start=%s,setpts=PTS-STARTPTS[v2];[v1][v2]concat=n=2:v=1:a=0[outv]",
                $ss1, $ss1
            );
            $fcv = escapeshellarg($filterV);
            $cmdV = "{$ffmpeg} -y -i {$in} -filter_complex {$fcv} -map [outv] "
                  . "-an -c:v libx264 -preset fast -crf 20 {$out} 2>&1";
            exec($cmdV, $res, $codeV);
            if ($codeV !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
                @unlink($tmp);
                throw new \RuntimeException(
                    'ffmpeg trim failed: ' . substr(implode("\n", array_slice($res, -5)), 0, 400)
                );
            }
        }

        if (!@rename($tmp, $absVideoPath)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not replace the original video after trimming.');
        }
    }
}
