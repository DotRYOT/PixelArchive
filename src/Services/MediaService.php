<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\Database;
use InvalidArgumentException;
use RuntimeException;

final class MediaService
{
    private const VIDEO_MIMES = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-matroska' => 'mkv',
        'video/x-msvideo' => 'avi',
        'video/mpeg' => 'mpeg',
    ];
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
    ];

    public function __construct(
        private readonly Database $database,
        private readonly Config $config,
        private readonly FFmpegService $ffmpeg,
        private readonly TagService $tagService
    ) {
    }

    public function all(): array
    {
        $favoriteVideos = $this->database->getFavoriteVideoIds();
        $favoriteImages = $this->database->getFavoriteImageIds();
        $videos = array_map(fn(array $item): array => $this->decorate($item, 'video', $favoriteVideos), $this->database->getVideos());
        $images = array_map(fn(array $item): array => $this->decorate($item, 'image', $favoriteImages), $this->database->getImages());
        $all = array_merge($videos, $images);
        usort($all, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $all;
    }

    public function videos(): array
    {
        $favorites = $this->database->getFavoriteVideoIds();
        return array_map(fn(array $item): array => $this->decorate($item, 'video', $favorites), $this->database->getVideos());
    }

    public function images(): array
    {
        $favorites = $this->database->getFavoriteImageIds();
        return array_map(fn(array $item): array => $this->decorate($item, 'image', $favorites), $this->database->getImages());
    }

    public function find(string $id, string $type): ?array
    {
        $record = $type === 'video'
            ? $this->database->findVideo($id)
            : ($type === 'image' ? $this->database->findImage($id) : null);
        return $record === null ? null : $this->decorate($record, $type);
    }

    public function createVideoFromFile(string $source, string $title, ?string $extension = null, array $tags = []): array
    {
        $id = self::uuid();
        $extension ??= $this->config->string('videoExtension');
        if (!preg_match('/^[a-zA-Z0-9]{2,8}$/', $extension)) {
            throw new InvalidArgumentException('Invalid video extension.');
        }
        $filename = $id . '.' . strtolower($extension);
        $target = $this->config->storagePath('videos') . DIRECTORY_SEPARATOR . $filename;
        if (!rename($source, $target)) {
            if (!copy($source, $target)) {
                throw new RuntimeException('Unable to place the downloaded video in storage.');
            }
            @unlink($source);
        }
        try {
            $metadata = $this->ffmpeg->probe($target);
            if (!$metadata['hasVideo']) {
                throw new RuntimeException('The uploaded file does not contain a video stream.');
            }
            $item = [
                'id' => $id,
                'title' => self::cleanTitle($title, 'Untitled video'),
                'filename' => $filename,
                'path' => 'videos/' . $filename,
                'thumbnail' => $this->ffmpeg->thumbnail($target, $id),
                'tags' => $this->tagService->normalize($tags),
                'duration' => $metadata['duration'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'created_at' => date(DATE_ATOM),
            ];
            $this->database->createVideo($item);
            return $this->decorate($item, 'video');
        } catch (\Throwable $exception) {
            @unlink($target);
            $this->removeThumbnailFiles($id);
            throw $exception;
        }
    }

    public function upload(array $files): array
    {
        $accepted = [];
        foreach (['videos', 'images', 'video', 'image', 'files'] as $field) {
            if (isset($files[$field])) {
                foreach ($this->flattenFiles($files[$field]) as $file) {
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $accepted[] = $file;
                    }
                }
            }
        }
        if ($accepted === []) {
            throw new InvalidArgumentException('Choose at least one video or image to upload.');
        }
        if (count($accepted) > $this->config->int('maxFiles')) {
            throw new InvalidArgumentException('You can upload at most ' . $this->config->int('maxFiles') . ' files at a time.');
        }

        $created = [];
        $errors = [];
        foreach ($accepted as $file) {
            try {
                $created[] = $this->uploadOne($file);
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($created === []) {
            throw new RuntimeException(implode(' ', $errors) ?: 'No files could be uploaded.');
        }
        return ['items' => $created, 'errors' => $errors];
    }

    public function update(string $id, string $type, array $changes): ?array
    {
        $allowed = [];
        if (array_key_exists('title', $changes)) {
            $title = is_string($changes['title']) ? trim($changes['title']) : '';
            preg_match_all('/./us', $title, $titleCharacters);
            if ($title === '' || count($titleCharacters[0] ?? []) > 160) {
                throw new InvalidArgumentException('Title must be between 1 and 160 characters.');
            }
            $allowed['title'] = $title;
        }
        if (array_key_exists('tags', $changes)) {
            $allowed['tags'] = $this->tagService->normalize($changes['tags']);
        }
        if ($allowed === []) {
            throw new InvalidArgumentException('No supported metadata changes were supplied.');
        }
        $updated = $type === 'video'
            ? $this->database->updateVideo($id, $allowed)
            : ($type === 'image' ? $this->database->updateImage($id, $allowed) : null);
        return $updated === null ? null : $this->decorate($updated, $type);
    }

    public function toggleFavorite(string $id, string $type): bool
    {
        if ($type === 'video' && $this->database->findVideo($id) !== null) {
            $favorite = $this->database->isFavoriteVideo($id);
            $favorite ? $this->database->removeFavoriteVideo($id) : $this->database->addFavoriteVideo($id);
            return !$favorite;
        }
        if ($type === 'image' && $this->database->findImage($id) !== null) {
            $favorite = $this->database->isFavoriteImage($id);
            $favorite ? $this->database->removeFavoriteImage($id) : $this->database->addFavoriteImage($id);
            return !$favorite;
        }
        throw new InvalidArgumentException('Media item not found.');
    }

    public function delete(string $id, string $type): bool
    {
        if (!in_array($type, ['video', 'image'], true)) {
            throw new InvalidArgumentException('Media type must be video or image.');
        }
        $record = $type === 'video' ? $this->database->findVideo($id) : $this->database->findImage($id);
        if ($record === null) {
            return false;
        }
        $path = $this->resolvePath($record, $type);
        $stagedPath = null;
        if (is_file($path)) {
            $extension = pathinfo($record['filename'], PATHINFO_EXTENSION);
            $stagedPath = $this->config->storagePath('temp') . DIRECTORY_SEPARATOR .
                $id . '-delete-' . bin2hex(random_bytes(4)) . '.' . $extension;
            if (!rename($path, $stagedPath)) {
                throw new RuntimeException('The media file could not be staged for deletion.');
            }
        }
        try {
            $deleted = $type === 'video'
                ? $this->database->deleteVideo($id)
                : $this->database->deleteImage($id);
            if ($deleted === null) {
                if ($stagedPath !== null && is_file($stagedPath)) {
                    rename($stagedPath, $path);
                }
                return false;
            }
        } catch (\Throwable $exception) {
            if ($stagedPath !== null && is_file($stagedPath) && !rename($stagedPath, $path)) {
                throw new RuntimeException('Media metadata deletion failed and the file could not be restored.', 0, $exception);
            }
            throw $exception;
        }
        if ($stagedPath !== null && is_file($stagedPath) && !unlink($stagedPath)) {
            throw new RuntimeException('The media entry was deleted, but its staged file could not be removed.');
        }
        if ($type === 'video') {
            $this->removeThumbnailFiles($id);
        }
        return true;
    }

    public function trim(string $id, mixed $startInput, mixed $endInput): array
    {
        $record = $this->database->findVideo($id);
        if ($record === null) {
            throw new InvalidArgumentException('Video not found.');
        }
        $start = self::parseTime($startInput);
        $end = self::parseTime($endInput);
        $duration = (float)($record['duration'] ?? 0);
        if ($start >= $end || $start < 0 || $end > $duration || $end - $start >= $duration) {
            throw new InvalidArgumentException('Start and end must be within the video duration, with start before end.');
        }
        $source = $this->resolvePath($record, 'video');
        $temp = $this->config->storagePath('temp') . DIRECTORY_SEPARATOR . $id . '-trim.mp4';
        $this->ffmpeg->trim($source, $temp, $start, $end, $duration);
        $backup = $source . '.bak';
        $replacement = $this->config->storagePath('videos') . DIRECTORY_SEPARATOR . $id . '.mp4';
        if (!rename($source, $backup)) {
            @unlink($temp);
            throw new RuntimeException('Unable to stage the original video for replacement.');
        }
        if (!rename($temp, $replacement)) {
            if (!rename($backup, $source)) {
                throw new RuntimeException('Unable to replace the trimmed video or restore the original.');
            }
            @unlink($temp);
            throw new RuntimeException('Unable to replace the video with the trimmed version.');
        }
        $updated = null;
        try {
            $metadata = $this->ffmpeg->probe($replacement);
            $thumbnail = $this->ffmpeg->thumbnail($replacement, $id);
            $updated = $this->database->updateVideo($id, [
                'filename' => $id . '.mp4',
                'path' => 'videos/' . $id . '.mp4',
                'duration' => $metadata['duration'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'thumbnail' => $thumbnail,
            ]);
            if ($updated === null) {
                throw new RuntimeException('Video metadata disappeared during trimming.');
            }
        } catch (\Throwable $exception) {
            @unlink($replacement);
            if (!rename($backup, $source)) {
                throw new RuntimeException('Trim failed and the original could not be restored.', 0, $exception);
            }
            $this->ffmpeg->thumbnail($source, $id);
            throw $exception;
        }
        if (is_file($backup) && !unlink($backup)) {
            error_log('Unable to remove the original video backup after trimming: ' . $id);
        }
        if ($updated === null) {
            throw new RuntimeException('Trimmed video metadata was not saved.');
        }
        return $this->decorate($updated, 'video');
    }

    public function fileFor(string $id, string $type): ?array
    {
        $record = $this->find($id, $type);
        if ($record === null) {
            return null;
        }
        return ['path' => $this->resolvePath($record, $type), 'record' => $record];
    }

    public function thumbnailPath(string $id): ?string
    {
        if ($this->database->findVideo($id) === null) {
            return null;
        }
        foreach (['jpg', 'svg'] as $extension) {
            $path = $this->config->storagePath('cache') . DIRECTORY_SEPARATOR . $id . '.' . $extension;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function parseTime(mixed $value): float
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Enter a valid time in seconds or HH:MM:SS.');
        }
        $text = trim((string)$value);
        if (preg_match('/^\d+(?:\.\d+)?$/', $text)) {
            return (float)$text;
        }
        if (preg_match('/^(\d{1,3}):([0-5]?\d):([0-5]?\d(?:\.\d+)?)$/', $text, $matches)) {
            return ((int)$matches[1] * 3600) + ((int)$matches[2] * 60) + (float)$matches[3];
        }
        throw new InvalidArgumentException('Enter a valid time in seconds or HH:MM:SS.');
    }

    public function decorate(array $item, string $type, ?array $favoriteIds = null): array
    {
        $id = (string)($item['id'] ?? '');
        $item['type'] = $type;
        if ($favoriteIds === null) {
            $favoriteIds = $type === 'video'
                ? $this->database->getFavoriteVideoIds()
                : $this->database->getFavoriteImageIds();
        }
        $item['favorite'] = in_array($id, $favoriteIds, true);
        $item['url'] = '/media?id=' . rawurlencode($id) . '&type=' . $type;
        $item['thumbnail_url'] = $type === 'video'
            ? '/media?id=' . rawurlencode($id) . '&type=thumbnail'
            : $item['url'];
        return $item;
    }

    private function uploadOne(array $file): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError($error));
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Upload verification failed.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || !is_readable($tmp)) {
            throw new RuntimeException('The uploaded file is empty or unreadable.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = self::VIDEO_MIMES[$mime] ?? self::IMAGE_MIMES[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Unsupported media type.');
        }
        $title = self::cleanTitle(pathinfo((string)($file['name'] ?? ''), PATHINFO_FILENAME), 'Untitled media');
        $id = self::uuid();

        if (isset(self::IMAGE_MIMES[$mime])) {
            $dimensions = getimagesize($tmp);
            if ($dimensions === false || !move_uploaded_file($tmp, $this->config->storagePath('images') . DIRECTORY_SEPARATOR . $id . '.' . $extension)) {
                throw new RuntimeException('Unable to save the uploaded image.');
            }
            $filename = $id . '.' . $extension;
            $item = [
                'id' => $id,
                'title' => $title,
                'filename' => $filename,
                'path' => 'images/' . $filename,
                'tags' => [],
                'width' => (int)$dimensions[0],
                'height' => (int)$dimensions[1],
                'created_at' => date(DATE_ATOM),
            ];
            try {
                $this->database->createImage($item);
            } catch (\Throwable $exception) {
                $storedPath = $this->config->storagePath('images') . DIRECTORY_SEPARATOR . $filename;
                if (is_file($storedPath) && !unlink($storedPath)) {
                    throw new RuntimeException('Image registration failed and the uploaded file could not be removed.', 0, $exception);
                }
                throw $exception;
            }
            return $this->decorate($item, 'image');
        }

        $filename = $id . '.' . $extension;
        $target = $this->config->storagePath('videos') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Unable to save the uploaded video.');
        }
        try {
            $metadata = $this->ffmpeg->probe($target);
            if (!$metadata['hasVideo']) {
                throw new RuntimeException('The uploaded file does not contain a video stream.');
            }
            $item = [
                'id' => $id,
                'title' => $title,
                'filename' => $filename,
                'path' => 'videos/' . $filename,
                'thumbnail' => $this->ffmpeg->thumbnail($target, $id),
                'tags' => [],
                'duration' => $metadata['duration'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'created_at' => date(DATE_ATOM),
            ];
            $this->database->createVideo($item);
            return $this->decorate($item, 'video');
        } catch (\Throwable $exception) {
            @unlink($target);
            $this->removeThumbnailFiles($id);
            throw $exception;
        }
    }

    private function flattenFiles(mixed $field): array
    {
        if (!is_array($field)) {
            return [];
        }
        if (!isset($field['name']) || !is_array($field['name'])) {
            return [$field];
        }
        $files = [];
        foreach ($field['name'] as $index => $name) {
            $files[] = [
                'name' => $name,
                'type' => $field['type'][$index] ?? '',
                'tmp_name' => $field['tmp_name'][$index] ?? '',
                'error' => $field['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $field['size'][$index] ?? 0,
            ];
        }
        return $files;
    }

    private function resolvePath(array $record, string $type): string
    {
        $id = (string)($record['id'] ?? '');
        $filename = (string)($record['filename'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id) ||
            !preg_match('/^' . preg_quote($id, '/') . '\.[a-zA-Z0-9]{2,8}$/', $filename)) {
            throw new RuntimeException('Stored media path is invalid.');
        }
        $expected = $type === 'video' ? 'videos/' . $filename : 'images/' . $filename;
        if (($record['path'] ?? '') !== $expected) {
            throw new RuntimeException('Stored media path is invalid.');
        }
        return $this->config->storagePath($type === 'video' ? 'videos' : 'images') . DIRECTORY_SEPARATOR . $filename;
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The upload exceeds the configured size limit.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked the upload.',
            default => 'The upload failed.',
        };
    }

    private function removeThumbnailFiles(string $id): void
    {
        foreach (['jpg', 'svg'] as $extension) {
            $path = $this->config->storagePath('cache') . DIRECTORY_SEPARATOR . $id . '.' . $extension;
            if (is_file($path) && !unlink($path)) {
                error_log('Unable to remove cached thumbnail: ' . $id);
            }
        }
    }

    private static function cleanTitle(string $title, string $fallback): string
    {
        $title = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $title))) ?? '');
        if ($title === '') {
            return $fallback;
        }
        preg_match_all('/./us', $title, $characters);
        return implode('', array_slice($characters[0] ?? [], 0, 160));
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
