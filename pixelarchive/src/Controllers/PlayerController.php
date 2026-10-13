<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MediaService;
use App\Services\TagService;
use App\Utils\Config;
use App\Utils\Response;

/**
 * Video / image viewer page with autoplay queue context.
 */
final class PlayerController
{
    public function show(): void
    {
        $id = (string) ($_GET['id'] ?? '');
        if (!\App\Utils\Database::isValidId($id)) {
            Response::setStatusCode(404);
            echo view('views/pages/404.php', [], 'Not found');
            return;
        }

        $media = MediaService::make();
        $item = $media->find(MediaService::TYPE_VIDEO, $id)
             ?? $media->find(MediaService::TYPE_IMAGE, $id);

        if ($item === null) {
            Response::setStatusCode(404);
            echo view('views/pages/404.php', [], 'Not found');
            return;
        }

        $type = isset($item['duration']) && strpos((string) ($item['path'] ?? ''), 'videos/') === 0
            ? MediaService::TYPE_VIDEO
            : (str_starts_with((string) ($item['path'] ?? ''), 'images/')
                ? MediaService::TYPE_IMAGE
                : MediaService::TYPE_VIDEO);
        // Authoritative check via master lists:
        if ($media->find(MediaService::TYPE_VIDEO, $id) !== null) {
            $type = MediaService::TYPE_VIDEO;
        } elseif ($media->find(MediaService::TYPE_IMAGE, $id) !== null) {
            $type = MediaService::TYPE_IMAGE;
        }

        $playlistId = (string) ($_GET['playlist'] ?? '');
        $tag = (string) ($_GET['tag'] ?? '');

        $queue = $media->queueFor($item, $playlistId, $tag);
        $index = 0;
        foreach ($queue as $i => $q) {
            if (($q['id'] ?? '') === $id) {
                $index = $i;
                break;
            }
        }

        echo view('views/pages/player.php', [
            'item'       => $media->normalize($item, $type, self::isFav($type, $id)),
            'type'       => $type,
            'queue'      => $queue,
            'index'      => $index,
            'playlistId' => $playlistId,
            'tag'        => $tag,
            'playlists'  => \App\Utils\Database::instance()->getPlaylists(),
            'tags'       => TagService::make()->tagsOf($item),
        ], (string) ($item['title'] ?? 'Player'));
    }

    private static function isFav(string $type, string $id): bool
    {
        $db = \App\Utils\Database::instance();
        return $type === MediaService::TYPE_IMAGE ? $db->isFavoriteImage($id) : $db->isFavoriteVideo($id);
    }
}
