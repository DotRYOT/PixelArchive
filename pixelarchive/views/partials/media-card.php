<?php

declare(strict_types=1);

use App\Services\MediaService;

/**
 * Shared media card partial.
 * Expects: $item (normalized array with id/title/type/tags/is_favorite/thumbnail/path/duration)
 */
$media = MediaService::make();
$id    = (string) ($item['id'] ?? '');
$type  = (string) ($item['type'] ?? 'video');
$title = (string) ($item['title'] ?? 'Untitled');
$href  = '/player?id=' . urlencode($id)
       . (isset($item['__ctx']) ? $item['__ctx'] : '');
$thumb = $media->thumbnailUrl($item + ['type' => $type]);
$duration = isset($item['duration']) ? (float) $item['duration'] : null;
?>
<article class="card group relative"
         x-data="{ fav: <?= !empty($item['is_favorite']) ? 'true' : 'false' ?> }">
    <a href="<?= e($href) ?>" class="block rounded-lg overflow-hidden bg-neutral-900 aspect-video">
        <img src="<?= e($thumb) ?>" alt="<?= e($title) ?>" loading="lazy"
             class="w-full h-full object-cover transition duration-300 group-hover:scale-[1.03] group-hover:opacity-90">
        <?php if ($type === 'video'): ?>
        <span class="absolute bottom-2 right-2 text-[11px] font-medium bg-black/80 text-white px-1.5 py-0.5 rounded">
            <?= e(fmt_duration((float) ($item['duration'] ?? 0))) ?>
        </span>
        <span class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition text-[11px] bg-black/80 text-cyan-400 px-1.5 py-0.5 rounded flex items-center gap-1">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3"><path d="M8 5v14l11-7z"/></svg>
            Play
        </span>
        <?php else: ?>
        <span class="absolute top-2 left-2 text-[11px] bg-black/80 text-neutral-100 px-1.5 py-0.5 rounded">IMG</span>
        <?php endif; ?>
    </a>

    <!-- Favorite toggle -->
    <button type="button" aria-label="Toggle favorite" title="Toggle favorite"
            @click="fav = !fav; api('/api/media/update', {id: '<?= e($id) ?>', type: '<?= e($type) ?>', favorite: fav})
                        .then(r => { if (!r.ok) throw 0; }).catch(() => { fav = !fav; toast('Could not update favorite', 'error'); })"
            class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/60 border border-neutral-800 hover:bg-neutral-900 transition"
            :class="fav ? 'text-cyan-400' : 'text-neutral-500 hover:text-white'">
        <svg viewBox="0 0 24 24" class="w-4 h-4" :fill="fav ? 'currentColor' : 'none'"
             stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
            <path d="M12 21s-7.5-4.6-9.3-9A5.6 5.6 0 0 1 12 6.5 5.6 5.6 0 0 1 21.3 12c-1.8 4.4-9.3 9-9.3 9z"/>
        </svg>
    </button>

    <div class="p-3">
        <h3 class="text-sm font-medium text-white truncate" title="<?= e($title) ?>">
            <a href="<?= e($href) ?>" class="hover:text-cyan-400 transition"><?= e($title) ?></a>
        </h3>
        <?php if (!empty($item['tags'])): ?>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <?php foreach (array_slice($item['tags'], 0, 4) as $tag): ?>
            <a class="chip" href="/?tag=<?= urlencode($tag) ?>#filter-tag"><?= e($tag) ?></a>
            <?php endforeach; ?>
            <?php if (count($item['tags']) > 4): ?>
            <span class="chip text-neutral-500">+<?= count($item['tags']) - 4 ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</article>
