<?php

declare(strict_types=1);

use App\Services\MediaService;

/**
 * Video/image viewer page. Uses the shared player partial for videos and a
 * full-size image viewer with prev/next + lightbox nav for images.
 */
$media = MediaService::make();
$isVideo = $type === MediaService::TYPE_VIDEO;
$siblings = array_values(array_filter($queue, static fn ($q) => ($q['id'] ?? '') !== ($item['id'] ?? '')));
?>
<div class="space-y-6 max-w-5xl mx-auto"
     x-data="{ fav: <?= !empty($item['is_favorite']) ? 'true' : 'false' ?> }">

    <?php if ($isVideo): ?>
        <?php require dirname(__DIR__) . '/partials/video-player.php'; ?>
    <?php else: ?>
        <!-- Image viewer -->
        <div x-data="imageViewer(<?= json_encode(array_map(static fn ($s) => [
                'id' => (string) $s['id'],
                'src' => $media->mediaUrl($s),
                'title' => (string) ($s['title'] ?? ''),
            ], $siblings), JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"
             @keydown.window="handleKey($event)">
            <div class="relative rounded-lg overflow-hidden border border-neutral-800 bg-black">
                <img src="<?= e($media->mediaUrl($item)) ?>" alt="<?= e((string) $item['title']) ?>"
                     class="w-full max-h-[75vh] object-contain bg-black" x-ref="img">

                <button x-show="hasPrev()" @click="prev()" class="nav-arrow left-3" aria-label="Previous image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6"><path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button x-show="hasNext()" @click="nextImg()" class="nav-arrow right-3" aria-label="Next image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <button @click="zoom = !zoom" class="absolute top-3 right-3 btn-icon bg-black/60 border border-neutral-800" aria-label="Toggle zoom">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M11 8v6M8 11h6" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Meta row -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-xl font-semibold text-white break-words"><?= e((string) $item['title']) ?></h1>
            <p class="text-sm text-neutral-400 mt-1">
                <?= e($isVideo ? 'Video' : 'Image') ?>
                <?php if ($isVideo && ($item['duration'] ?? 0) > 0): ?> · <?= e(fmt_duration((float) $item['duration'])) ?><?php endif; ?>
                <?php if (($item['width'] ?? 0) > 0): ?> · <?= (int) $item['width'] ?>×<?= (int) $item['height'] ?><?php endif; ?>
                · <?= e(date('M j, Y', strtotime((string) ($item['created_at'] ?? 'now')))) ?>
            </p>
            <div class="mt-3 flex flex-wrap gap-1.5" id="player-tags"
                 x-data="{ tags: <?= json_encode($tags, JSON_HEX_APOS | JSON_HEX_QUOT) ?> }">
                <template x-for="t in tags" :key="t">
                    <a :href="'/?tag=' + encodeURIComponent(t)" class="chip cursor-pointer"><span x-text="t"></span></a>
                </template>
                <button @click="addTagPrompt(tags)" class="chip border-dashed text-neutral-400 hover:text-cyan-400">+ tag</button>
                <input type="text" id="new-tag-input" class="input !w-32 hidden" placeholder="new tag"
                       @keydown.enter="addTagFromInput(tags)" @blur="addTagFromInput(tags)">
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button @click="fav = !fav; api('/api/media/update', {id: '<?= e((string) $item['id']) ?>', type: '<?= e($type) ?>', favorite: fav})
                        .then(r => { if (!r.ok) throw 0; }).catch(() => { fav = !fav; toast('Could not update favorite', 'error'); })"
                    class="btn" :class="fav ? 'btn-primary' : 'btn-secondary'" aria-pressed="fav">
                <svg viewBox="0 0 24 24" class="w-4 h-4" :fill="fav ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 21s-7.5-4.6-9.3-9A5.6 5.6 0 0 1 12 6.5 5.6 5.6 0 0 1 21.3 12c-1.8 4.4-9.3 9-9.3 9z"/>
                </svg>
                <span x-text="fav ? 'Favorited' : 'Favorite'"></span>
            </button>
            <button class="btn btn-danger" @click="confirmDelete('<?= e((string) $item['id']) ?>', '<?= e($type) ?>')">
                Delete
            </button>
        </div>
    </div>

    <!-- Queue strip -->
    <?php if ($isVideo && $siblings !== []): ?>
    <section>
        <h2 class="text-sm uppercase tracking-wider text-neutral-400 font-medium mb-3">Up next in queue</h2>
        <div class="flex gap-4 overflow-x-auto pb-2">
            <?php foreach (array_slice($siblings, 0, 12) as $s): ?>
            <a href="/player?id=<?= e((string) $s['id']) ?><?= e($ctx ?? '') ?>" class="w-44 shrink-0 group">
                <div class="rounded-lg overflow-hidden border border-neutral-800 aspect-video bg-neutral-900">
                    <img src="<?= e($media->thumbnailUrl($s + ['type' => 'video'])) ?>" loading="lazy" alt=""
                         class="w-full h-full object-cover group-hover:opacity-80 transition">
                </div>
                <p class="text-xs text-neutral-300 mt-1.5 truncate group-hover:text-cyan-400 transition"><?= e((string) $s['title']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
