<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\Database;

/**
 * Central media operations: registration, thumbnails, filtering/search,
 * safe file resolution, deletion, and favorite toggling.
 */
final class MediaService
{
    public const TYPE_VIDEO = 'video';
    public const TYPE_IMAGE = 'image';

    private const IMAGE_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/bmp'  => 'bmp',
    ];

    private const VIDEO_MIME = [
        'video/mp4'         => 'mp4',
        'video/x-matroska'  => 'mkv',
        'video/quicktime'   => 'mov',
        'video/x-msvideo'   => 'avi',
        'video/webm'        => 'webm',
        'video/mpeg'        => 'mpeg',
        'video/x-flv'       => 'flv',
        'application/octet-stream' => null, // allow, extension decides
    ];

    public function __construct(
        private readonly Database $db = new Database(),
        private readonly FFmpegService $ffmpeg = new FFmpegService(),
    ) {
        // Allow default construction via singleton accessor too.
    }

    public static function make(): self
    {
        return new self(Database::instance(), new FFmpegService());
    }

    /* ------------------------------------------------------------------ */
    /* Paths                                                               */
    /* ------------------------------------------------------------------ */

    /** Resolve a relative stored path (e.g. "videos/x.mp4") to an absolute one, guarding traversal. */
    public function resolveStoredPath(string $rel): ?string
    {
        $rel = str_replace('\\', '/', trim($rel));
        if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) {
            return null;
        }
        [$sub, $name] = array_pad(explode('/', $rel, 2), 2, '');
        if (!in_array($sub, ['videos', 'images', 'cache', 'temp'], true) || $name === '') {
            return null;
        }
        if (!preg_match('/^[\w.\- ]+$/u', $name)) {
            return null;
        }
        $abs = Config::storagePath($sub, $name);
        return is_file($abs) ? $abs : null;
    }

    /** Web-accessible URL for a stored file, served through /media. */
    public function url(string $rel): string
    {
        return '/media?file=' . rawurlencode($rel);
    }

    public function thumbnailUrl(array $item): string
    {
        $thumb = (string) ($item['thumbnail'] ?? '');
        if ($thumb !== '' && $this->resolveStoredPath($thumb) !== null) {
            return $this->url($thumb);
        }
        return '/placeholder?type=' . urlencode((string) ($item['type'] ?? 'video'));
    }

    public function mediaUrl(array $item): string
    {
        $rel = (string) ($item['path'] ?? '');
        return $rel !== '' ? $this->url($rel) : '#';
    }

    /* ------------------------------------------------------------------ */
    /* Normalization / listing                                             */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array<string,mixed>> All media, videos + images, with type & favorite flags. */
    public function all(): array
    {
        $items = [];
        foreach ($this->db->getVideos() as $v) {
            $items[] = $this->normalize($v, self::TYPE_VIDEO, $this->db->isFavoriteVideo((string) $v['id']));
        }
        foreach ($this->db->getImages() as $i) {
            $items[] = $this->normalize($i, self::TYPE_IMAGE, $this->db->isFavoriteImage((string) $i['id']));
        }
        usort($items, static fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return $items;
    }

    /** @param array<string,mixed> $item */
    public function normalize(array $item, string $type, bool $favorite): array
    {
        $item['type'] = $type;
        $item['is_favorite'] = $favorite;
        $item['tags'] = array_values(array_filter((array) ($item['tags'] ?? []), 'is_string'));
        return $item;
    }

    /** @return array<int,array<string,mixed>> */
    public function of(string $type): array
    {
        return $type === self::TYPE_IMAGE ? $this->db->getImages() : $this->db->getVideos();
    }

    public function find(string $type, string $id): ?array
    {
        return $type === self::TYPE_IMAGE ? $this->db->findImage($id) : $this->db->findVideo($id);
    }

    /**
     * Filter/search over all media.
     * @param array<string,mixed> $q keys: q, type, tag, favorite, playlist
     * @return array<int,array<string,mixed>>
     */
    public function filter(array $q): array
    {
        $items = $this->all();

        $search = mb_strtolower(trim((string) ($q['q'] ?? '')));
        if ($search !== '') {
            $items = array_values(array_filter($items, static function (array $it) use ($search): bool {
                if (str_contains(mb_strtolower((string) $it['title']), $search)) {
                    return true;
                }
                foreach ($it['tags'] as $tag) {
                    if (str_contains(mb_strtolower($tag), $search)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $type = (string) ($q['type'] ?? 'all');
        if ($type === self::TYPE_VIDEO || $type === self::TYPE_IMAGE) {
            $items = array_values(array_filter($items, static fn ($it) => $it['type'] === $type));
        }

        if (($q['favorite'] ?? '') === '1') {
            $items = array_values(array_filter($items, static fn ($it) => $it['is_favorite']));
        }

        $tag = mb_strtolower(trim((string) ($q['tag'] ?? '')));
        if ($tag !== '') {
            $items = array_values(array_filter(
                $items,
                static fn (array $it) => in_array($tag, array_map('mb_strtolower', $it['tags']), true)
            ));
        }

        $playlistId = (string) ($q['playlist'] ?? '');
        if ($playlistId !== '' && Database::isValidId($playlistId)) {
            $playlist = null;
            foreach ($this->db->getPlaylists() as $p) {
                if (($p['id'] ?? '') === $playlistId) {
                    $playlist = $p;
                    break;
                }
            }
            if ($playlist !== null) {
                $ptags = array_map('mb_strtolower', array_filter((array) ($playlist['tags'] ?? []), 'is_string'));
                $items = array_values(array_filter(
                    $items,
                    static fn (array $it) => count(array_intersect($ptags, array_map('mb_strtolower', $it['tags']))) > 0
                ));
            }
        }

        return $items;
    }

    /** @return array<int,string> Every tag in use, lowercased & unique. */
    public function allTags(): array
    {
        $tags = [];
        foreach ($this->all() as $it) {
            foreach ($it['tags'] as $t) {
                $tags[mb_strtolower($t)] = true;
            }
        }
        $out = array_keys($tags);
        sort($out);
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Registration (uploads & downloads)                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Register an already-placed file in storage/videos (relative name only).
     * @throws \RuntimeException on unreadable media.
     */
    public function registerVideo(string $storedName, ?string $title = null): array
    {
        $abs = Config::storagePath('videos', $storedName);
        $info = $this->ffmpeg->probe($abs);
        $thumbRel = $this->generateVideoThumbnail('videos/' . $storedName);

        return $this->db->createVideo([
            'title'     => $title !== null && trim($title) !== '' ? trim($title) : pathinfo($storedName, PATHINFO_FILENAME),
            'filename'  => $storedName,
            'path'      => 'videos/' . $storedName,
            'thumbnail' => $thumbRel,
            'duration'  => $info['duration'],
            'width'     => $info['width'],
            'height'    => $info['height'],
        ]);
    }

    /** Register an image already placed in storage/images. */
    public function registerImage(string $storedName, ?string $title = null): array
    {
        $abs = Config::storagePath('images', $storedName);
        $size = @getimagesize($abs);
        return $this->db->createImage([
            'title'    => $title !== null && trim($title) !== '' ? trim($title) : pathinfo($storedName, PATHINFO_FILENAME),
            'filename' => $storedName,
            'path'     => 'images/' . $storedName,
            'width'    => $size ? $size[0] : 0,
            'height'   => $size ? $size[1] : 0,
        ]);
    }

    /**
     * Generate the cache thumbnail for a video rel path ("videos/x.mp4").
     * Returns the rel thumbnail path or a placeholder-safe empty string.
     */
    public function generateVideoThumbnail(string $videoRel): string
    {
        $abs = $this->resolveStoredPath($videoRel);
        if ($abs === null) {
            return '';
        }
        $id = preg_replace('/[^a-z0-9]/i', '', strtolower(pathinfo(basename($videoRel), PATHINFO_FILENAME)));
        // Prefer the media id when known: look it up by filename.
        foreach ($this->db->getVideos() as $v) {
            if (($v['path'] ?? '') === $videoRel) {
                $id = (string) $v['id'];
                break;
            }
        }
        if ($id === '') {
            $id = bin2hex(random_bytes(8));
        }
        $thumbRel = 'cache/' . $id . '.jpg';
        $thumbAbs = $this->resolveOrBuildCachePath($thumbRel);
        $made = $this->ffmpeg->extractThumbnail($abs, $thumbAbs);
        return $made !== null ? $thumbRel : '';
    }

    private function resolveOrBuildCachePath(string $thumbRel): string
    {
        $id = basename($thumbRel, '.jpg');
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $id)) {
            throw new \InvalidArgumentException('Bad thumbnail id.');
        }
        $dir = Config::storageDir('cache');
        return $dir . DIRECTORY_SEPARATOR . $id . '.jpg';
    }

    /* ------------------------------------------------------------------ */
    /* Upload validation                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Validate + move one uploaded tmp file into storage. Never trusts client names.
     * @param array<string,mixed> $file a $_FILES entry
     * @return array<string,mixed> the created media record
     * @throws \RuntimeException with a readable message
     */
    public function ingestUpload(array $file, string $type): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed (PHP error code ' . $err . ').');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && !is_file($tmp))) {
            throw new \RuntimeException('Uploaded file is missing.');
        }

        $mime = $this->detectMime($tmp);
        $extFromMime = null;

        if ($type === self::TYPE_IMAGE) {
            $extFromMime = self::IMAGE_MIME[$mime] ?? null;
            if ($extFromMime === null) {
                throw new \RuntimeException('Unsupported image type: ' . htmlspecialchars($mime, ENT_QUOTES));
            }
        } else {
            if (!array_key_exists($mime, self::VIDEO_MIME)) {
                throw new \RuntimeException('Unsupported video type: ' . htmlspecialchars($mime, ENT_QUOTES));
            }
            $clientExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'mkv', 'mov', 'avi', 'webm', 'mpeg', 'mpg', 'flv', 'm4v'];
            if (!in_array($clientExt, $allowed, true)) {
                throw new \RuntimeException('Disallowed video extension: ' . htmlspecialchars($clientExt, ENT_QUOTES));
            }
            $extFromMime = self::VIDEO_MIME[$mime] ?? $clientExt;
        }

        $safeName = $this->uniqueStorageName($extFromMime ?: 'bin', $type);
        $destDir = Config::storageDir($type === self::TYPE_IMAGE ? 'images' : 'videos');
        $dest = $destDir . DIRECTORY_SEPARATOR . $safeName;

        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : @rename($tmp, $dest);
        if (!$moved) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }

        $rawTitle = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        $title = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $rawTitle) ?: 'Untitled', 0, 120);

        return $type === self::TYPE_IMAGE
            ? $this->registerImage($safeName, $title)
            : $this->registerVideo($safeName, $title);
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $m = finfo_file($fi, $path);
                finfo_close($fi);
                if (is_string($m) && $m !== '') {
                    return $m;
                }
            }
        }
        return 'application/octet-stream';
    }

    private function uniqueStorageName(string $ext, string $type): string
    {
        $prefix = $type === self::TYPE_IMAGE ? 'img' : 'vid';
        do {
            $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-z0-9]/', '', strtolower($ext));
            $exists = is_file(Config::storagePath($type === self::TYPE_IMAGE ? 'images' : 'videos', $name));
        } while ($exists);
        return $name;
    }

    /* ------------------------------------------------------------------ */
    /* Mutations                                                           */
    /* ------------------------------------------------------------------ */

    /** @param array<string,mixed> $patch */
    public function update(string $type, string $id, array $patch): ?array
    {
        return $type === self::TYPE_IMAGE ? $this->db->updateImage($id, $patch) : $this->db->updateVideo($id, $patch);
    }

    public function toggleFavorite(string $type, string $id): bool
    {
        if ($type === self::TYPE_IMAGE) {
            if ($this->db->isFavoriteImage($id)) {
                $this->db->removeFavoriteImage($id);
                return false;
            }
            return $this->db->addFavoriteImage($id);
        }
        if ($this->db->isFavoriteVideo($id)) {
            $this->db->removeFavoriteVideo($id);
            return false;
        }
        return $this->db->addFavoriteVideo($id);
    }

    /** Delete metadata + files for an item. */
    public function delete(string $type, string $id): bool
    {
        $item = $this->find($type, $id);
        if ($item === null) {
            return false;
        }
        $rel = (string) ($item['path'] ?? '');
        $abs = $rel !== '' ? $this->resolveStoredPath($rel) : null;
        if ($abs === null && $rel !== '') {
            // File may be recorded but missing — still purge metadata below.
            $guess = Config::storagePath(dirname(str_replace('\\', '/', $rel)), basename($rel));
            $abs = is_file($guess) ? $guess : null;
        }
        if ($abs !== null) {
            @unlink($abs);
        }
        $thumbAbs = Config::storagePath('cache', $id . '.jpg');
        if (is_file($thumbAbs)) {
            @unlink($thumbAbs);
        }
        return $type === self::TYPE_IMAGE ? $this->db->deleteImage($id) : $this->db->deleteVideo($id);
    }

    /**
     * Trim a section out of a video, then refresh duration + thumbnail.
     * @return array{duration:float}
     */
    public function trimVideo(string $id, float $start, float $end): array
    {
        $video = $this->db->findVideo($id);
        if ($video === null) {
            throw new \RuntimeException('Video not found.');
        }
        $abs = $this->resolveStoredPath((string) ($video['path'] ?? ''));
        if ($abs === null) {
            throw new \RuntimeException('Video file is missing from storage.');
        }
        $this->ffmpeg->removeSection($abs, $start, $end);

        $info = $this->ffmpeg->probe($abs);
        $this->db->updateVideo($id, ['duration' => $info['duration'], 'width' => $info['width'], 'height' => $info['height']]);
        $thumbRel = $this->generateVideoThumbnail((string) $video['path']);
        if ($thumbRel !== '') {
            $this->db->updateVideo($id, ['thumbnail' => $thumbRel]);
        }
        return ['duration' => $info['duration']];
    }

    /* ------------------------------------------------------------------ */
    /* Playback queue helpers                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Ordered list of playable items for autoplay context.
     * @return array<int,array<string,mixed>>
     */
    public function queueFor(array $current, string $playlistId = '', string $tag = ''): array
    {
        if ($playlistId !== '' || $tag !== '') {
            $list = $this->filter([
                'type'      => self::TYPE_VIDEO,
                'playlist'  => $playlistId,
                'tag'       => $tag,
            ]);
            if ($list !== []) {
                return $list;
            }
        }
        // Default queue: all videos, newest first (already sorted by all()).
        return array_values(array_filter($this->all(), static fn ($it) => $it['type'] === self::TYPE_VIDEO));
    }
}
