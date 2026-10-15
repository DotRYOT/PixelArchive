<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MediaService;
use App\Utils\Database;

final class PlayerController
{
    public function __construct(
        private readonly MediaService $media,
        private readonly Database $database
    ) {
    }

    public function show(): never
    {
        $requestedId = $_GET['id'] ?? '';
        $id = is_string($requestedId) ? $requestedId : '';
        $type = ($_GET['type'] ?? '') === 'image' ? 'image' : 'video';
        $item = preg_match('/^[a-f0-9-]{36}$/i', $id) ? $this->media->find($id, $type) : null;
        if ($item === null) {
            app_render('player', [
                'pageTitle' => 'Media not found',
                'activeNav' => 'home',
                'item' => null,
                'queue' => [],
                'previousImage' => null,
                'nextImage' => null,
            ], 404);
        }

        $contextValue = $_GET['tag'] ?? '';
        $context = is_string($contextValue) ? substr(trim($contextValue), 0, 1000) : '';
        $requestedPlaylist = $_GET['playlist'] ?? '';
        $playlistId = is_string($requestedPlaylist) ? $requestedPlaylist : '';
        if ($playlistId !== '') {
            foreach ($this->database->getPlaylists() as $playlist) {
                if (($playlist['id'] ?? '') === $playlistId) {
                    $context = implode(',', $playlist['tags'] ?? []);
                    break;
                }
            }
        }
        $contextTags = array_filter(array_map('strtolower', explode(',', $context)));
        $queue = array_values(array_filter($this->media->videos(), static function (array $video) use ($contextTags): bool {
            if ($contextTags === []) {
                return true;
            }
            return count(array_intersect($contextTags, array_map('strtolower', $video['tags'] ?? []))) > 0;
        }));
        $next = null;
        if ($type === 'video') {
            foreach ($queue as $index => $video) {
                if ($video['id'] === $id && isset($queue[$index + 1])) {
                    $next = $queue[$index + 1];
                    break;
                }
            }
        }

        $images = $this->media->images();
        $index = null;
        foreach ($images as $imageIndex => $image) {
            if ($image['id'] === $id) {
                $index = $imageIndex;
                break;
            }
        }
        app_render('player', [
            'pageTitle' => $item['title'],
            'activeNav' => $type === 'image' ? 'gallery' : 'home',
            'item' => $item,
            'queue' => $queue,
            'nextItem' => $next,
            'previousImage' => $index !== null && $index > 0 ? $images[$index - 1] : null,
            'nextImage' => $index !== null && isset($images[$index + 1]) ? $images[$index + 1] : null,
            'queueContext' => $context,
        ]);
    }
}
