<?php
declare(strict_types=1);

namespace App\Utils;

use RuntimeException;

final class Database
{
    private const FILES = [
        'videos.json',
        'images.json',
        'favoritevideos.json',
        'favoriteimages.json',
        'playlists.json',
    ];

    public function __construct(private readonly string $directory)
    {
        foreach (self::FILES as $file) {
            $path = $this->path($file);
            if (!is_file($path) || filesize($path) === 0) {
                $this->saveFile($file, []);
            }
        }
    }

    public function getVideos(): array { return $this->loadFile('videos.json'); }
    public function getImages(): array { return $this->loadFile('images.json'); }
    public function findVideo(string $id): ?array { return $this->find($this->getVideos(), $id); }
    public function findImage(string $id): ?array { return $this->find($this->getImages(), $id); }

    public function createVideo(array $data): array
    {
        return $this->changeFile('videos.json', static function (array &$items) use ($data): array {
            $items[] = $data;
            return $data;
        });
    }

    public function createImage(array $data): array
    {
        return $this->changeFile('images.json', static function (array &$items) use ($data): array {
            $items[] = $data;
            return $data;
        });
    }

    public function updateVideo(string $id, array $data): ?array { return $this->update('videos.json', $id, $data); }
    public function updateImage(string $id, array $data): ?array { return $this->update('images.json', $id, $data); }

    public function deleteVideo(string $id): ?array
    {
        $deleted = $this->remove('videos.json', $id);
        if ($deleted !== null) {
            $this->removeFavoriteId('favoritevideos.json', $id);
        }
        return $deleted;
    }

    public function deleteImage(string $id): ?array
    {
        $deleted = $this->remove('images.json', $id);
        if ($deleted !== null) {
            $this->removeFavoriteId('favoriteimages.json', $id);
        }
        return $deleted;
    }

    public function getFavoriteVideoIds(): array { return $this->loadFile('favoritevideos.json'); }
    public function getFavoriteImageIds(): array { return $this->loadFile('favoriteimages.json'); }
    public function addFavoriteVideo(string $id): void { $this->addFavorite('favoritevideos.json', $id); }
    public function removeFavoriteVideo(string $id): void { $this->removeFavoriteId('favoritevideos.json', $id); }
    public function addFavoriteImage(string $id): void { $this->addFavorite('favoriteimages.json', $id); }
    public function removeFavoriteImage(string $id): void { $this->removeFavoriteId('favoriteimages.json', $id); }
    public function isFavoriteVideo(string $id): bool { return in_array($id, $this->getFavoriteVideoIds(), true); }
    public function isFavoriteImage(string $id): bool { return in_array($id, $this->getFavoriteImageIds(), true); }
    public function getFavoriteVideos(): array { return $this->joinFavorites($this->getFavoriteVideoIds(), $this->getVideos()); }
    public function getFavoriteImages(): array { return $this->joinFavorites($this->getFavoriteImageIds(), $this->getImages()); }
    public function getPlaylists(): array { return $this->loadFile('playlists.json'); }

    public function createPlaylist(array $data): array
    {
        return $this->changeFile('playlists.json', static function (array &$items) use ($data): array {
            $items[] = $data;
            return $data;
        });
    }

    public function deletePlaylist(string $id): bool
    {
        return $this->changeFile('playlists.json', static function (array &$items) use ($id): bool {
            $before = count($items);
            $items = array_values(array_filter($items, static fn(array $item): bool => ($item['id'] ?? '') !== $id));
            return count($items) !== $before;
        });
    }

    private function loadFile(string $file): array
    {
        $lock = $this->lock($file, LOCK_SH);
        try {
            return $this->readUnlocked($file);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function saveFile(string $file, array $data): void
    {
        $lock = $this->lock($file, LOCK_EX);
        try {
            $this->writeUnlocked($file, $data);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function changeFile(string $file, callable $change): mixed
    {
        $lock = $this->lock($file, LOCK_EX);
        try {
            $items = $this->readUnlocked($file);
            $result = $change($items);
            $this->writeUnlocked($file, $items);
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readUnlocked(string $file): array
    {
        $path = $this->path($file);
        if (!is_file($path) || filesize($path) === 0) {
            return [];
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read storage file: ' . $file);
        }
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Storage file contains invalid JSON: ' . $file, 0, $exception);
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('Storage file must contain a JSON array: ' . $file);
        }
        return $decoded;
    }

    private function writeUnlocked(string $file, array $data): void
    {
        $encoded = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $handle = fopen($this->path($file), 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open storage file: ' . $file);
        }
        try {
            if (!ftruncate($handle, 0) || rewind($handle) === false ||
                fwrite($handle, $encoded . PHP_EOL) === false || !fflush($handle)) {
                throw new RuntimeException('Unable to write storage file: ' . $file);
            }
        } finally {
            fclose($handle);
        }
    }

    private function lock(string $file, int $mode)
    {
        $handle = fopen($this->path($file) . '.lock', 'c');
        if ($handle === false || !flock($handle, $mode)) {
            throw new RuntimeException('Unable to lock storage file: ' . $file);
        }
        return $handle;
    }

    private function path(string $file): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $file;
    }

    private function find(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['id'] ?? null) === $id) {
                return $item;
            }
        }
        return null;
    }

    private function update(string $file, string $id, array $data): ?array
    {
        return $this->changeFile($file, static function (array &$items) use ($id, $data): ?array {
            foreach ($items as $index => $item) {
                if (is_array($item) && ($item['id'] ?? null) === $id) {
                    $items[$index] = array_merge($item, $data);
                    return $items[$index];
                }
            }
            return null;
        });
    }

    private function remove(string $file, string $id): ?array
    {
        return $this->changeFile($file, static function (array &$items) use ($id): ?array {
            $deleted = null;
            $items = array_values(array_filter($items, static function (mixed $item) use ($id, &$deleted): bool {
                if (is_array($item) && ($item['id'] ?? null) === $id) {
                    $deleted = $item;
                    return false;
                }
                return true;
            }));
            return $deleted;
        });
    }

    private function addFavorite(string $file, string $id): void
    {
        $this->changeFile($file, static function (array &$ids) use ($id): null {
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            return null;
        });
    }

    private function removeFavoriteId(string $file, string $id): void
    {
        $this->changeFile($file, static function (array &$ids) use ($id): null {
            $ids = array_values(array_filter($ids, static fn(mixed $favoriteId): bool => $favoriteId !== $id));
            return null;
        });
    }

    private function joinFavorites(array $ids, array $items): array
    {
        $byId = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $byId[$item['id']] = $item;
            }
        }
        $favorites = [];
        foreach ($ids as $id) {
            if (is_string($id) && isset($byId[$id])) {
                $favorites[] = $byId[$id];
            }
        }
        return $favorites;
    }
}
