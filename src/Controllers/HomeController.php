<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MediaService;
use App\Utils\Database;

final class HomeController
{
    public function __construct(
        private readonly MediaService $media,
        private readonly Database $database
    ) {
    }

    public function index(): never
    {
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim(substr($_GET['q'], 0, 200)) : '';
        $requestedType = $_GET['type'] ?? '';
        $type = is_string($requestedType) && in_array($requestedType, ['video', 'image'], true) ? $requestedType : '';
        $tag = isset($_GET['tag']) && is_string($_GET['tag']) ? trim(substr($_GET['tag'], 0, 160)) : '';
        $favorites = ($_GET['favorites'] ?? '') === '1';
        if ($favorites) {
            $videoIds = $this->database->getFavoriteVideoIds();
            $imageIds = $this->database->getFavoriteImageIds();
            $items = array_merge(
                array_map(fn(array $item): array => $this->media->decorate($item, 'video', $videoIds), $this->database->getFavoriteVideos()),
                array_map(fn(array $item): array => $this->media->decorate($item, 'image', $imageIds), $this->database->getFavoriteImages())
            );
        } else {
            $items = $this->media->all();
        }
        $items = $this->filter($items, $query, $type, $tag);

        app_render('home', [
            'pageTitle' => $favorites ? 'Favorites' : 'Your library',
            'activeNav' => $favorites ? 'favorites' : 'home',
            'items' => $items,
            'query' => $query,
            'typeFilter' => $type,
            'tagFilter' => $tag,
            'favoritesOnly' => $favorites,
            'allTags' => $this->allTags(),
        ]);
    }

    public function gallery(): never
    {
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim(substr($_GET['q'], 0, 200)) : '';
        $tag = isset($_GET['tag']) && is_string($_GET['tag']) ? trim(substr($_GET['tag'], 0, 160)) : '';
        $items = $this->filter($this->media->images(), $query, 'image', $tag);
        app_render('gallery', [
            'pageTitle' => 'Image gallery',
            'activeNav' => 'gallery',
            'items' => $items,
            'query' => $query,
            'tagFilter' => $tag,
            'allTags' => $this->allTags(),
        ]);
    }

    public function playlists(): never
    {
        $playlists = $this->database->getPlaylists();
        $requestedId = $_GET['id'] ?? '';
        $selectedId = is_string($requestedId) ? $requestedId : '';
        $selected = null;
        foreach ($playlists as $playlist) {
            if (($playlist['id'] ?? '') === $selectedId) {
                $selected = $playlist;
                break;
            }
        }
        $items = $selected === null
            ? []
            : array_values(array_filter($this->media->all(), static function (array $item) use ($selected): bool {
                $wanted = array_map('strtolower', $selected['tags'] ?? []);
                $present = array_map('strtolower', $item['tags'] ?? []);
                return count(array_intersect($wanted, $present)) > 0;
            }));
        app_render('playlists', [
            'pageTitle' => 'Playlists',
            'activeNav' => 'playlists',
            'playlists' => $playlists,
            'selectedPlaylist' => $selected,
            'items' => $items,
            'allTags' => $this->allTags(),
        ]);
    }

    private function filter(array $items, string $query, string $type, string $tag): array
    {
        $search = strtolower($query);
        $wantedTag = strtolower($tag);
        return array_values(array_filter($items, static function (array $item) use ($search, $type, $wantedTag): bool {
            if ($type !== '' && ($item['type'] ?? '') !== $type) {
                return false;
            }
            if ($wantedTag !== '' && !in_array($wantedTag, array_map('strtolower', $item['tags'] ?? []), true)) {
                return false;
            }
            $haystack = strtolower((string)($item['title'] ?? '') . ' ' . implode(' ', $item['tags'] ?? []));
            return $search === '' || str_contains($haystack, $search);
        }));
    }

    private function allTags(): array
    {
        $tags = [];
        foreach (array_merge($this->media->videos(), $this->media->images()) as $item) {
            foreach ($item['tags'] ?? [] as $tag) {
                $tags[strtolower($tag)] = $tag;
            }
        }
        natcasesort($tags);
        return array_values($tags);
    }
}
