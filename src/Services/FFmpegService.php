<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use RuntimeException;

final class FFmpegService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function probe(string $path): array
    {
        $result = $this->run($this->command('ffprobe', [
            '-v', 'error', '-show_entries', 'format=duration:stream=codec_type,width,height',
            '-of', 'json', $path,
        ]));
        if ($result['code'] !== 0) {
            throw new RuntimeException('Could not read media metadata. ' . $this->message($result['stderr']));
        }
        $metadata = json_decode($result['stdout'], true);
        if (!is_array($metadata)) {
            throw new RuntimeException('ffprobe returned invalid media metadata.');
        }
        $video = null;
        foreach (($metadata['streams'] ?? []) as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $video = $stream;
                break;
            }
        }
        return [
            'duration' => max(0.0, (float)($metadata['format']['duration'] ?? 0)),
            'width' => max(0, (int)($video['width'] ?? 0)),
            'height' => max(0, (int)($video['height'] ?? 0)),
            'hasVideo' => $video !== null,
            'hasAudio' => $this->hasAudio($metadata['streams'] ?? []),
        ];
    }

    public function thumbnail(string $source, string $id): string
    {
        $this->assertId($id);
        $cache = $this->config->storagePath('cache');
        $target = $cache . DIRECTORY_SEPARATOR . $id . '.jpg';
        $width = $this->config->int('thumbWidth');
        $height = $this->config->int('thumbHeight');
        $offset = $this->config->int('frameTime');
        $filter = 'scale=' . $width . ':' . $height . ':force_original_aspect_ratio=decrease,pad=' .
            $width . ':' . $height . ':(ow-iw)/2:(oh-ih)/2:black';
        $result = $this->run($this->command('ffmpeg', [
            '-hide_banner', '-loglevel', 'error', '-y', '-ss', (string)$offset, '-i', $source,
            '-frames:v', '1', '-vf', $filter, '-q:v', '3', $target,
        ]));
        if ($result['code'] === 0 && is_file($target)) {
            return 'cache/' . $id . '.jpg';
        }

        if (is_file($target)) {
            if (!unlink($target)) {
                throw new RuntimeException('Unable to clear an outdated thumbnail.');
            }
        }
        $placeholder = $cache . DIRECTORY_SEPARATOR . $id . '.svg';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '">' .
            '<rect width="100%" height="100%" fill="#0a0a0a"/>' .
            '<path d="M' . (int)($width * .43) . ' ' . (int)($height * .35) . 'v' . (int)($height * .3) .
            'l' . (int)($width * .22) . ' -' . (int)($height * .15) . 'z" fill="#22d3ee"/>' .
            '<text x="50%" y="88%" fill="#a3a3a3" text-anchor="middle" font-family="sans-serif" font-size="16">Preview unavailable</text></svg>';
        if (file_put_contents($placeholder, $svg, LOCK_EX) === false) {
            throw new RuntimeException('Unable to create thumbnail placeholder.');
        }
        return 'cache/' . $id . '.svg';
    }

    public function trim(string $source, string $target, float $start, float $end, float $duration): void
    {
        if (!is_finite($start) || !is_finite($end) || $start < 0 || $start >= $end || $end > $duration) {
            throw new RuntimeException('The trim range must be within the media duration.');
        }
        $before = number_format($start, 3, '.', '');
        $after = number_format($end, 3, '.', '');
        $total = number_format($duration, 3, '.', '');
        $metadata = $this->probe($source);
        if (!$metadata['hasVideo']) {
            throw new RuntimeException('The selected media does not contain a video stream.');
        }

        if ($start == 0.0) {
            $filters = '[0:v]trim=start=' . $after . ':end=' . $total . ',setpts=PTS-STARTPTS[v]';
            $maps = ['-map', '[v]'];
            if ($metadata['hasAudio']) {
                $filters .= ';[0:a]atrim=start=' . $after . ':end=' . $total . ',asetpts=PTS-STARTPTS[a]';
                array_push($maps, '-map', '[a]');
            }
        } elseif ($end >= $duration) {
            $filters = '[0:v]trim=start=0:end=' . $before . ',setpts=PTS-STARTPTS[v]';
            $maps = ['-map', '[v]'];
            if ($metadata['hasAudio']) {
                $filters .= ';[0:a]atrim=start=0:end=' . $before . ',asetpts=PTS-STARTPTS[a]';
                array_push($maps, '-map', '[a]');
            }
        } else {
            $filters = '[0:v]trim=start=0:end=' . $before . ',setpts=PTS-STARTPTS[v0];' .
                '[0:v]trim=start=' . $after . ':end=' . $total . ',setpts=PTS-STARTPTS[v1];';
            $maps = ['-map', '[v]'];
            if ($metadata['hasAudio']) {
                $filters .= '[0:a]atrim=start=0:end=' . $before . ',asetpts=PTS-STARTPTS[a0];' .
                    '[0:a]atrim=start=' . $after . ':end=' . $total . ',asetpts=PTS-STARTPTS[a1];' .
                    '[v0][a0][v1][a1]concat=n=2:v=1:a=1[v][a]';
                array_push($maps, '-map', '[a]');
            } else {
                $filters .= '[v0][v1]concat=n=2:v=1:a=0[v]';
            }
        }
        $arguments = [
            '-hide_banner', '-loglevel', 'error', '-y', '-i', $source,
            '-filter_complex', $filters,
            ...$maps,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20',
        ];
        if ($metadata['hasAudio']) {
            array_push($arguments, '-c:a', 'aac');
        }
        array_push($arguments, '-movflags', '+faststart', '-f', 'mp4', $target);
        $result = $this->run($this->command('ffmpeg', $arguments));
        if ($result['code'] !== 0 || !is_file($target) || filesize($target) === 0) {
            @unlink($target);
            throw new RuntimeException('Video trimming failed. ' . $this->message($result['stderr']));
        }
    }

    private function run(string $command): array
    {
        $errorFile = tempnam($this->config->storagePath('temp'), 'ffmpeg-');
        if ($errorFile === false) {
            throw new RuntimeException('Unable to create media-processing log.');
        }
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errorFile, 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($errorFile);
            throw new RuntimeException('Unable to start the media processing tool.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code = proc_close($process);
        $stderr = file_get_contents($errorFile);
        @unlink($errorFile);
        return [
            'code' => $code,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }

    private function command(string $executable, array $arguments): string
    {
        return $executable . ' ' . implode(' ', array_map('escapeshellarg', $arguments));
    }

    private function hasAudio(array $streams): bool
    {
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                return true;
            }
        }
        return false;
    }

    private function assertId(string $id): void
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) {
            throw new RuntimeException('Invalid media identifier.');
        }
    }

    private function message(string $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error) ?? '');
        return $message === '' ? 'Check that ffmpeg and ffprobe are available.' : substr($message, 0, 400);
    }
}
