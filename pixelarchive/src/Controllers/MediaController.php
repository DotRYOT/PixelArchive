<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MediaService;
use App\Services\TagService;
use App\Utils\Config;
use App\Utils\Database;
use App\Utils\Response;

/**
 * Media browsing, file serving, and all /api/media/* mutations.
 */
final class MediaController
{
    /* ------------------------------ GET pages ------------------------------ */

    public function gallery(): void
    {
        $media = MediaService::make();
        $items = $media->filter(array_merge($_GET, ['type' => MediaService::TYPE_IMAGE]));
        echo view('views/pages/gallery.php', [
            'items' => $items,
            'tags'  => $media->allTags(),
            'query' => [
                'q'        => (string) ($_GET['q'] ?? ''),
                'tag'      => (string) ($_GET['tag'] ?? ''),
                'favorite' => (string) ($_GET['favorite'] ?? ''),
            ],
        ], 'Gallery');
    }

    public function playlists(): void
    {
        $db = Database::instance();
        $media = MediaService::make();
        $tagSvc = TagService::make();

        $playlists = array_map(static function (array $p) use ($media, $tagSvc): array {
            $videos = array_values(array_filter(
                $media->filter(['playlist' => (string) ($p['id'] ?? '')]),
                static fn ($it) => $it['type'] === MediaService::TYPE_VIDEO
            ));
            $p['video_count'] = count($videos);
            $p['__first'] = $videos[0]['id'] ?? null;
            return $p;
        }, $db->getPlaylists());

        echo view('views/pages/playlists.php', [
            'playlists' => $playlists,
            'tags'      => $media->allTags(),
        ], 'Playlists');
    }

    /* --------------------------- File streaming ---------------------------- */

    /** Serve a storage file safely via ?file=videos/x.mp4 (keeps URLs portable). */
    public function serveFile(): void
    {
        $rel = (string) ($_GET['file'] ?? '');
        $abs = MediaService::make()->resolveStoredPath($rel);
        if ($abs === null) {
            Response::error('File not found.', 404);
        }

        $types = [
            'mp4' => 'video/mp4', 'mkv' => 'video/x-matroska', 'webm' => 'video/webm',
            'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'avif' => 'image/avif', 'bmp' => 'image/bmp',
        ];
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = $types[$ext] ?? 'application/octet-stream';

        $size = filesize($abs);
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . (string) ($size === false ? 0 : $size));
        header('Content-Disposition: inline; filename="' . basename($abs) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
    }

    /** SVG placeholder when a thumbnail could not be generated. */
    public function placeholder(): void
    {
        $isImage = (($_GET['type'] ?? '') === 'image');
        $icon = $isImage
            ? '<path d="M4 5h16v14H4z" fill="none" stroke="#22d3ee" stroke-width="1.5"/><circle cx="9" cy="10" r="1.6" fill="#22d3ee"/><path d="M5 18l5-5 3 3 4-4 2 2" fill="none" stroke="#22d3ee" stroke-width="1.5"/>'
            : '<path d="M9 6.5v11l9-5.5z" fill="#22d3ee"/>';
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 16" preserveAspectRatio="xMidYMid slice">'
           . '<rect width="24" height="16" fill="#0a0a0a"/>'
           . '<g transform="translate(9,1) scale(0.58)">' . $icon . '</g></svg>';
    }

    /* ------------------------------ API ------------------------------------ */

    /** POST /api/media/update — title, tags, favorite toggle. */
    public function apiUpdate(): void
    {
        $in = Response::input();
        $id = Response::idParam($in);
        $type = Response::strParam($in, 'type') === MediaService::TYPE_IMAGE
            ? MediaService::TYPE_IMAGE : MediaService::TYPE_VIDEO;

        $media = MediaService::make();
        $item = $media->find($type, $id);
        if ($item === null) {
            Response::error('Media not found.', 404);
        }

        $patch = [];

        if (array_key_exists('title', $in)) {
            $title = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', Response::strParam($in, 'title')) ?? '', 0, 160);
            if ($title === '') {
                Response::error('Title cannot be empty.');
            }
            $patch['title'] = $title;
        }

        if (array_key_exists('tags', $in)) {
            $patch['tags'] = TagService::make()->sanitizeList(is_array($in['tags']) ? $in['tags'] : (string) $in['tags']);
        }

        if (array_key_exists('favorite', $in)) {
            $want = filter_var($in['favorite'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($want !== null) {
                $isFav = $type === MediaService::TYPE_IMAGE
                    ? Database::instance()->isFavoriteImage($id)
                    : Database::instance()->isFavoriteVideo($id);
                if ($want !== $isFav) {
                    $media->toggleFavorite($type, $id);
                }
            }
        }

        if ($patch !== []) {
            $item = $media->update($type, $id, $patch) ?? $item;
        }

        Response::ok([
            'item' => $media->normalize(
                $item,
                $type,
                $type === MediaService::TYPE_IMAGE
                    ? Database::instance()->isFavoriteImage($id)
                    : Database::instance()->isFavoriteVideo($id)
            ),
        ]);
    }

    /** POST /api/media/trim — remove a section from a video. */
    public function apiTrim(): void
    {
        $in = Response::input();
        $id = Response::idParam($in);

        $start = self::parseTime(Response::strParam($in, 'start'));
        $end = self::parseTime(Response::strParam($in, 'end'));
        if ($start === null || $end === null) {
            Response::error('Times must be seconds or HH:MM:SS.');
        }
        if ($end <= $start) {
            Response::error('Start must be before end.');
        }

        try {
            $result = MediaService::make()->trimVideo($id, $start, $end);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

        Response::ok(['duration' => $result['duration']]);
    }

    /** POST /api/media/delete */
    public function apiDelete(): void
    {
        $in = Response::input();
        $id = Response::idParam($in);
        $type = Response::strParam($in, 'type') === MediaService::TYPE_IMAGE
            ? MediaService::TYPE_IMAGE : MediaService::TYPE_VIDEO;

        if (!MediaService::make()->delete($type, $id)) {
            Response::error('Media not found.', 404);
        }
        Response::ok();
    }

    /** POST /api/playlists — create. */
    public function apiPlaylistCreate(): void
    {
        $in = Response::input();
        $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', Response::strParam($in, 'name')) ?? '', 0, 80);
        if ($name === '') {
            Response::error('Playlist name is required.');
        }
        $tags = TagService::make()->sanitizeList($in['tags'] ?? '');
        if ($tags === []) {
            Response::error('A playlist needs at least one tag.');
        }
        $pl = Database::instance()->createPlaylist(['name' => $name, 'tags' => $tags]);
        Response::ok(['playlist' => $pl]);
    }

    /** POST /api/playlists/delete */
    public function apiPlaylistDelete(): void
    {
        $id = Response::strParam(Response::input(), 'id');
        if (!Database::isValidId($id) || !Database::instance()->deletePlaylist($id)) {
            Response::error('Playlist not found.', 404);
        }
        Response::ok();
    }

    /** Parse "90", "90.5", "1:30", or "01:30:45" into seconds. */
    public static function parseTime(string $s): ?float
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d+(\.\d+)?$/', $s)) {
            return (float) $s;
        }
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2})(\.\d+)?)?$/', $s, $m)) {
            // h:m:s or m:s
            if (isset($m[3]) && $m[3] !== '') {
                return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (float) ($m[3] . ($m[4] ?? ''));
            }
            return ((int) $m[1]) * 60 + (float) $m[2];
        }
        return null;
    }
}
