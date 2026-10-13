<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DownloadService;
use App\Services\MediaService;
use App\Utils\Config;
use App\Utils\Response;

/**
 * Upload page + POST handlers for uploads and yt-dlp downloads.
 */
final class UploadController
{
    public function form(): void
    {
        echo view('views/pages/upload.php', [
            'maxFiles' => Config::getInt('maxFiles', 100),
        ], 'Upload');
    }

    /** POST /api/upload — multipart form: videos[] and images[]. */
    public function apiUpload(): void
    {
        $maxFiles = max(1, Config::getInt('maxFiles', 100));
        $media = MediaService::make();

        $files = self::flattenFiles($_FILES);
        if ($files === []) {
            Response::error('No files were received.', 422);
        }
        if (count($files) > $maxFiles) {
            Response::error('Too many files in one request (max ' . $maxFiles . ').', 422);
        }

        $created = [];
        $errors = [];
        foreach ($files as $f) {
            $kind = (string) ($f['__kind'] ?? 'video');
            try {
                $created[] = $media->ingestUpload($f, $kind);
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($created === []) {
            Response::error(implode(' ', array_slice($errors, 0, 3)) ?: 'Nothing could be uploaded.', 422);
        }

        Response::ok([
            'count'   => count($created),
            'created' => count($created),
            'errors'  => $errors,
        ]);
    }

    /** POST /api/download — { url, title? } */
    public function apiDownload(): void
    {
        $in = Response::input();
        $url = Response::strParam($in, 'url');
        $title = Response::strParam($in, 'title');

        try {
            $item = DownloadService::make()->download($url, $title);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

        Response::ok(['item' => $item]);
    }

    /**
     * Normalize PHP's awkward multi-file $_FILES structure into a flat list.
     * Supports inputs named videos[] / images[] as well as single fields.
     * @return array<int,array<string,mixed>>
     */
    private static function flattenFiles(array $files): array
    {
        $out = [];
        foreach ($files as $field => $entry) {
            if (!is_array($entry) || !isset($entry['name'])) {
                continue;
            }
            $kind = stripos((string) $field, 'image') !== false
                ? MediaService::TYPE_IMAGE
                : MediaService::TYPE_VIDEO;

            if (is_array($entry['name'])) {
                foreach (array_keys($entry['name']) as $i) {
                    if ((int) $entry['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $out[] = [
                        'name'     => (string) $entry['name'][$i],
                        'type'     => (string) ($entry['type'][$i] ?? ''),
                        'tmp_name' => (string) $entry['tmp_name'][$i],
                        'error'    => (int) $entry['error'][$i],
                        'size'     => (int) $entry['size'][$i],
                        '__kind'   => $kind,
                    ];
                }
            } else {
                if ((int) $entry['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $out[] = [
                    'name'     => (string) $entry['name'],
                    'type'     => (string) ($entry['type'] ?? ''),
                    'tmp_name' => (string) $entry['tmp_name'],
                    'error'    => (int) $entry['error'],
                    'size'     => (int) $entry['size'],
                    '__kind'   => $kind,
                ];
            }
        }
        return $out;
    }
}
