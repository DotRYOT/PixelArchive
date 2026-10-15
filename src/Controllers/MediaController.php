<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MediaService;
use App\Utils\Database;
use App\Utils\Response;
use InvalidArgumentException;
use RuntimeException;

final class MediaController
{
    public function __construct(
        private readonly MediaService $media,
        private readonly Database $database
    ) {
    }

    public function update(): never
    {
        try {
            $input = app_request_data();
            if (!is_string($input['action'] ?? 'metadata')) {
                throw new InvalidArgumentException('Action must be text.');
            }
            $action = (string)($input['action'] ?? 'metadata');
            if (!in_array($action, ['metadata', 'favorite', 'playlist_create', 'playlist_delete'], true)) {
                throw new InvalidArgumentException('Unsupported media action.');
            }
            if ($action === 'playlist_create') {
                $this->assertOnlyKeys($input, ['action', 'name', 'tags']);
                if (!is_string($input['name'] ?? null)) {
                    throw new InvalidArgumentException('Playlist name must be text.');
                }
                $name = trim($input['name']);
                preg_match_all('/./us', $name, $nameCharacters);
                if ($name === '' || count($nameCharacters[0] ?? []) > 80) {
                    throw new InvalidArgumentException('Playlist name must be between 1 and 80 characters.');
                }
                $tags = (new \App\Services\TagService())->normalize($input['tags'] ?? []);
                if ($tags === []) {
                    throw new InvalidArgumentException('Choose at least one tag for this playlist.');
                }
                $playlist = $this->database->createPlaylist([
                    'id' => self::uuid(),
                    'name' => $name,
                    'tags' => $tags,
                ]);
                Response::json(['success' => true, 'playlist' => $playlist]);
            }
            if ($action === 'playlist_delete') {
                $this->assertOnlyKeys($input, ['action', 'id']);
                $id = $this->requiredId($input['id'] ?? null);
                if (!$this->database->deletePlaylist($id)) {
                    Response::json(['success' => false, 'message' => 'Playlist not found.'], 404);
                }
                Response::json(['success' => true, 'message' => 'Playlist deleted.']);
            }

            $this->assertOnlyKeys(
                $input,
                $action === 'favorite' ? ['action', 'id', 'type'] : ['action', 'id', 'type', 'title', 'tags']
            );
            $id = $this->requiredId($input['id'] ?? null);
            $type = $this->requiredType($input['type'] ?? null);
            if ($action === 'favorite') {
                $favorite = $this->media->toggleFavorite($id, $type);
                Response::json(['success' => true, 'favorite' => $favorite]);
            }
            $updated = $this->media->update($id, $type, $input);
            if ($updated === null) {
                Response::json(['success' => false, 'message' => 'Media not found.'], 404);
            }
            Response::json(['success' => true, 'item' => $updated]);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function delete(): never
    {
        try {
            $input = app_request_data();
            $this->assertOnlyKeys($input, ['id', 'type']);
            $id = $this->requiredId($input['id'] ?? null);
            $type = $this->requiredType($input['type'] ?? null);
            if (!$this->media->delete($id, $type)) {
                Response::json(['success' => false, 'message' => 'Media not found.'], 404);
            }
            Response::json(['success' => true, 'message' => 'Media deleted.']);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function trim(): never
    {
        try {
            $input = app_request_data();
            $this->assertOnlyKeys($input, ['id', 'start', 'end']);
            $id = $this->requiredId($input['id'] ?? null);
            $item = $this->media->trim($id, $input['start'] ?? null, $input['end'] ?? null);
            Response::json(['success' => true, 'item' => $item, 'message' => 'Video section removed.']);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function serve(): never
    {
        $id = $_GET['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
            Response::html('Not found', 404);
        }
        $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
        if ($type === 'thumbnail') {
            $path = $this->media->thumbnailPath($id);
            if ($path === null) {
                Response::html('Not found', 404);
            }
            $mime = str_ends_with($path, '.svg') ? 'image/svg+xml' : 'image/jpeg';
            Response::file($path, $mime);
        }
        if (!in_array($type, ['video', 'image'], true)) {
            Response::html('Not found', 404);
        }
        $file = $this->media->fileFor($id, $type);
        if ($file === null || !is_file($file['path'])) {
            Response::html('Not found', 404);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['path']) ?: 'application/octet-stream';
        Response::file($file['path'], $mime, $file['record']['filename']);
    }

    private function requiredId(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9-]{36}$/i', $value)) {
            throw new InvalidArgumentException('A valid media identifier is required.');
        }
        return $value;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function requiredType(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['video', 'image'], true)) {
            throw new InvalidArgumentException('Media type must be video or image.');
        }
        return $value;
    }

    private function assertOnlyKeys(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported request field.');
            }
        }
    }
}
