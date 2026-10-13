<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;

/**
 * Wraps yt-dlp. Accepts a URL, downloads into storage/videos, and hands the
 * result to MediaService for registration. All shell args are escaped.
 */
final class DownloadService
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public static function make(): self
    {
        return new self(MediaService::make());
    }

    /** Basic sanity check before touching the shell. */
    public function validateUrl(string $url): bool
    {
        if (strlen($url) > 2000 || preg_match('/[\s\x00-\x1F]/', $url)) {
            return false;
        }
        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * @return array<string,mixed> The registered media record.
     * @throws \RuntimeException with a readable message on failure.
     */
    public function download(string $url, string $title = ''): array
    {
        if (!$this->validateUrl($url)) {
            throw new \RuntimeException('That does not look like a valid http(s) URL.');
        }

        $ext = preg_replace('/[^a-z0-9]/', '', strtolower(Config::getString('videoExtension', 'mp4'))) ?: 'mp4';
        $outTpl = rtrim(Config::storagePath('videos'), '/\\') . DIRECTORY_SEPARATOR
                . 'dl-%(id)s.' . $ext;

        $ytdlp = escapeshellarg(Config::getString('ytdlp', 'yt-dlp'));
        $argUrl = escapeshellarg($url);
        $argOut = escapeshellarg($outTpl);
        $argFmt = escapeshellarg('bestvideo[ext=' . $ext . ']+bestaudio[ext=m4a]/best[ext=' . $ext . ']/best');

        $cmd = "{$ytdlp} --no-playlist --newline -f {$argFmt} "
             . "--merge-output-format " . escapeshellarg($ext) . " "
             . "-o {$argOut} {$argUrl} 2>&1";

        $res = [];
        $code = 0;
        exec($cmd, $res, $code);

        if ($code !== 0) {
            $tail = implode("\n", array_slice($res, -6));
            throw new \RuntimeException('yt-dlp failed: ' . substr($tail, 0, 400));
        }

        // Find the newest dl-* file that appeared in storage/videos.
        $dir = Config::storageDir('videos');
        $best = null;
        $bestMtime = 0;
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . 'dl-*.' . $ext) as $f) {
            $m = (int) @filemtime((string) $f);
            if ($m > $bestMtime) {
                $bestMtime = $m;
                $best = basename((string) $f);
            }
        }
        if ($best === null) {
            throw new \RuntimeException('yt-dlp reported success but no output file was found.');
        }

        return $this->media->registerVideo($best, $title !== '' ? $title : null);
    }
}
