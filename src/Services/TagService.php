<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class TagService
{
    public function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Tags must be supplied as a list or comma-separated text.');
        }
        if (count($value) > 50) {
            throw new InvalidArgumentException('A media item can have at most 50 tags.');
        }

        $tags = [];
        $seen = [];
        foreach ($value as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException('Each tag must be text.');
            }
            $tag = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $tag) ?? '');
            if ($tag === '') {
                continue;
            }
            preg_match_all('/./us', $tag, $characters);
            if (count($characters[0] ?? []) > 40) {
                throw new InvalidArgumentException('Tags must be 40 characters or fewer.');
            }
            $key = strtolower($tag);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $tags[] = $tag;
            }
        }
        return $tags;
    }
}
