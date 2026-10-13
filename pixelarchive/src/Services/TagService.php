<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Database;

/**
 * Tag normalization and playlist matching.
 * Tags are stored trimmed, lowercase-safe display is applied at read time;
 * comparisons are case-insensitive.
 */
final class TagService
{
    public function __construct(
        private readonly Database $db = new Database(),
    ) {
    }

    public static function make(): self
    {
        return new self(Database::instance());
    }

    /**
     * Clean a raw tag list/string into canonical tags.
     * @param string|array<int,mixed> $input comma-separated string or array
     * @return array<int,string>
     */
    public function sanitizeList(string|array $input): array
    {
        $parts = is_array($input) ? $input : explode(',', $input);
        $tags = [];
        foreach ($parts as $p) {
            if (!is_scalar($p)) {
                continue;
            }
            $t = mb_strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $p) ?? ''));
            $t = mb_substr($t, 0, 40);
            if ($t !== '' && !in_array($t, $tags, true)) {
                $tags[] = $t;
            }
        }
        return $tags;
    }

    /** @return array<int,string> Tags currently on a media item. */
    public function tagsOf(array $item): array
    {
        return array_values(array_filter((array) ($item['tags'] ?? []), 'is_string'));
    }

    public function hasTag(array $item, string $tag): bool
    {
        $tag = mb_strtolower(trim($tag));
        foreach ($this->tagsOf($item) as $t) {
            if (mb_strtolower($t) === $tag) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add or remove one tag on a media item, persisting through MediaService.
     * @return array<int,string> the resulting tag list
     */
    public function modify(MediaService $media, string $type, string $id, string $tag, bool $add): array
    {
        $item = $media->find($type, $id);
        if ($item === null) {
            throw new \RuntimeException('Media not found.');
        }
        $tag = mb_strtolower(trim($tag));
        if ($tag === '' || mb_strlen($tag) > 40) {
            throw new \RuntimeException('Tag must be 1–40 characters.');
        }
        $tags = $this->tagsOf($item);
        $exists = $this->hasTag($item, $tag);
        if ($add && !$exists) {
            $tags[] = $tag;
        } elseif (!$add) {
            $tags = array_values(array_filter($tags, fn ($t) => !(mb_strtolower($t) === $tag)));
        }
        $media->update($type, $id, ['tags' => array_values(array_unique($tags))]);
        return array_values(array_unique($tags));
    }

    /**
     * Does a media item match a playlist (any shared tag)?
     * @param array<string,mixed> $playlist
     */
    public function matchesPlaylist(array $item, array $playlist): bool
    {
        $ptags = array_map('mb_strtolower', array_filter((array) ($playlist['tags'] ?? []), 'is_string'));
        $itags = array_map('mb_strtolower', $this->tagsOf($item));
        return array_intersect($ptags, $itags) !== [];
    }

    /** @return array<int,array<string,mixed>> Playlists a given item belongs to. */
    public function playlistsForItem(array $item): array
    {
        return array_values(array_filter(
            $this->db->getPlaylists(),
            fn (array $p) => $this->matchesPlaylist($item, $p)
        ));
    }
}
