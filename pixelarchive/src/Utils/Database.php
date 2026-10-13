<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * JSON-file-backed data layer.
 *
 * Files live in the storage directory and are loaded lazily per request.
 * Writes use file locking + atomic rename so concurrent requests cannot
 * corrupt the files. Favorites store ONLY ids — metadata lives in
 * videos.json / images.json and is joined on read.
 */
final class Database
{
    private static ?self $instance = null;

    /** @var array<string, array<int|string, mixed>|null> */
    private array $cache = [];

    private function __construct(private readonly string $storageDir)
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(Config::getString('storagePath', dirname(__DIR__, 2) . '/storage'));
        }
        return self::$instance;
    }

    /* ------------------------------------------------------------------ */
    /* Low-level                                                           */
    /* ------------------------------------------------------------------ */

    private function path(string $file): string
    {
        // Only known json filenames reach here; reject anything odd.
        if (!preg_match('/^[a-z]+\.json$/', $file)) {
            throw new \InvalidArgumentException('Invalid data file name.');
        }
        return rtrim($this->storageDir, '/\\') . DIRECTORY_SEPARATOR . $file;
    }

    /** @return array<int|string,mixed> */
    private function read(string $file): array
    {
        $key = $file;
        if (array_key_exists($key, $this->cache) && $this->cache[$key] !== null) {
            /** @var array<int|string,mixed> */
            return $this->cache[$key];
        }

        $path = $this->path($file);
        $data = [];
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        $this->cache[$key] = $data;
        return $data;
    }

    /** @param array<int|string,mixed> $data */
    private function write(string $file, array $data): void
    {
        $path = $this->path($file);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON for ' . $file);
        }

        // Locked write to a temp file, then atomic rename over the target.
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open temp file for writing: ' . $file);
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                throw new \RuntimeException('Could not lock file for writing: ' . $file);
            }
            fwrite($fh, $json);
            fflush($fh);
            flock($fh, LOCK_UN);
        } finally {
            fclose($fh);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not persist file: ' . $file);
        }

        $this->cache[$file] = $data;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); // version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    /* ------------------------------------------------------------------ */
    /* Videos                                                              */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array<string,mixed>> */
    public function getVideos(): array
    {
        /** @var array<int,array<string,mixed>> */
        return array_values($this->read('videos.json'));
    }

    /** @return array<string,mixed>|null */
    public function findVideo(string $id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        foreach ($this->getVideos() as $v) {
            if (($v['id'] ?? '') === $id) {
                return $v;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $data */
    public function createVideo(array $data): array
    {
        $videos = $this->read('videos.json');
        $video = array_merge([
            'id'         => self::uuid(),
            'title'      => 'Untitled',
            'filename'   => '',
            'path'       => '',
            'thumbnail'  => '',
            'tags'       => [],
            'duration'   => 0.0,
            'width'      => 0,
            'height'     => 0,
            'created_at' => date('c'),
        ], $data);
        $video['id'] = self::isValidId((string) ($data['id'] ?? '')) ? $data['id'] : $video['id'];
        $videos[] = $video;
        $this->write('videos.json', $videos);
        return $video;
    }

    /** @param array<string,mixed> $patch */
    public function updateVideo(string $id, array $patch): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $videos = $this->read('videos.json');
        $updated = null;
        foreach ($videos as $i => $v) {
            if (($v['id'] ?? '') === $id) {
                unset($patch['id']);
                $videos[$i] = array_merge($v, $patch);
                $updated = $videos[$i];
                break;
            }
        }
        if ($updated !== null) {
            $this->write('videos.json', $videos);
        }
        return $updated;
    }

    public function deleteVideo(string $id): bool
    {
        if (!self::isValidId($id)) {
            return false;
        }
        $videos = $this->read('videos.json');
        $before = count($videos);
        $videos = array_values(array_filter($videos, static fn ($v) => ($v['id'] ?? '') !== $id));
        if (count($videos) === $before) {
            return false;
        }
        $this->write('videos.json', $videos);
        $this->removeFavoriteVideo($id);
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Images                                                              */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array<string,mixed>> */
    public function getImages(): array
    {
        /** @var array<int,array<string,mixed>> */
        return array_values($this->read('images.json'));
    }

    /** @return array<string,mixed>|null */
    public function findImage(string $id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        foreach ($this->getImages() as $img) {
            if (($img['id'] ?? '') === $id) {
                return $img;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $data */
    public function createImage(array $data): array
    {
        $images = $this->read('images.json');
        $image = array_merge([
            'id'         => self::uuid(),
            'title'      => 'Untitled',
            'filename'   => '',
            'path'       => '',
            'tags'       => [],
            'width'      => 0,
            'height'     => 0,
            'created_at' => date('c'),
        ], $data);
        $image['id'] = self::isValidId((string) ($data['id'] ?? '')) ? $data['id'] : $image['id'];
        $images[] = $image;
        $this->write('images.json', $images);
        return $image;
    }

    /** @param array<string,mixed> $patch */
    public function updateImage(string $id, array $patch): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $images = $this->read('images.json');
        $updated = null;
        foreach ($images as $i => $img) {
            if (($img['id'] ?? '') === $id) {
                unset($patch['id']);
                $images[$i] = array_merge($img, $patch);
                $updated = $images[$i];
                break;
            }
        }
        if ($updated !== null) {
            $this->write('images.json', $images);
        }
        return $updated;
    }

    public function deleteImage(string $id): bool
    {
        if (!self::isValidId($id)) {
            return false;
        }
        $images = $this->read('images.json');
        $before = count($images);
        $images = array_values(array_filter($images, static fn ($i) => ($i['id'] ?? '') !== $id));
        if (count($images) === $before) {
            return false;
        }
        $this->write('images.json', $images);
        $this->removeFavoriteImage($id);
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Favorites — id arrays only, joined against master lists on read     */
    /* ------------------------------------------------------------------ */

    /** @return array<int,string> */
    public function getFavoriteVideoIds(): array
    {
        $ids = $this->read('favoritevideos.json');
        return array_values(array_filter($ids, 'is_string'));
    }

    public function addFavoriteVideo(string $id): bool
    {
        if ($this->findVideo($id) === null) {
            return false;
        }
        $ids = $this->getFavoriteVideoIds();
        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
            $this->write('favoritevideos.json', $ids);
        }
        return true;
    }

    public function removeFavoriteVideo(string $id): void
    {
        $ids = array_values(array_filter($this->getFavoriteVideoIds(), static fn ($f) => $f !== $id));
        $this->write('favoritevideos.json', $ids);
    }

    public function isFavoriteVideo(string $id): bool
    {
        return in_array($id, $this->getFavoriteVideoIds(), true);
    }

    /** @return array<int,array<string,mixed>> Full video objects for favorite ids. */
    public function getFavoriteVideos(): array
    {
        $index = [];
        foreach ($this->getVideos() as $v) {
            $index[$v['id'] ?? ''] = $v;
        }
        $out = [];
        foreach ($this->getFavoriteVideoIds() as $id) {
            if (isset($index[$id])) {
                $out[] = $index[$id];
            }
        }
        return $out;
    }

    /** @return array<int,string> */
    public function getFavoriteImageIds(): array
    {
        $ids = $this->read('favoriteimages.json');
        return array_values(array_filter($ids, 'is_string'));
    }

    public function addFavoriteImage(string $id): bool
    {
        if ($this->findImage($id) === null) {
            return false;
        }
        $ids = $this->getFavoriteImageIds();
        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
            $this->write('favoriteimages.json', $ids);
        }
        return true;
    }

    public function removeFavoriteImage(string $id): void
    {
        $ids = array_values(array_filter($this->getFavoriteImageIds(), static fn ($f) => $f !== $id));
        $this->write('favoriteimages.json', $ids);
    }

    public function isFavoriteImage(string $id): bool
    {
        return in_array($id, $this->getFavoriteImageIds(), true);
    }

    /** @return array<int,array<string,mixed>> Full image objects for favorite ids. */
    public function getFavoriteImages(): array
    {
        $index = [];
        foreach ($this->getImages() as $img) {
            $index[$img['id'] ?? ''] = $img;
        }
        $out = [];
        foreach ($this->getFavoriteImageIds() as $id) {
            if (isset($index[$id])) {
                $out[] = $index[$id];
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Playlists                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array<string,mixed>> */
    public function getPlaylists(): array
    {
        /** @var array<int,array<string,mixed>> */
        return array_values($this->read('playlists.json'));
    }

    /** @param array<string,mixed> $data */
    public function createPlaylist(array $data): array
    {
        $playlists = $this->read('playlists.json');
        $playlist = array_merge([
            'id'         => self::uuid(),
            'name'       => 'New playlist',
            'tags'       => [],
            'created_at' => date('c'),
        ], $data);
        $playlists[] = $playlist;
        $this->write('playlists.json', $playlists);
        return $playlist;
    }

    public function deletePlaylist(string $id): bool
    {
        $playlists = $this->read('playlists.json');
        $before = count($playlists);
        $playlists = array_values(array_filter($playlists, static fn ($p) => ($p['id'] ?? '') !== $id));
        if (count($playlists) === $before) {
            return false;
        }
        $this->write('playlists.json', $playlists);
        return true;
    }
}
