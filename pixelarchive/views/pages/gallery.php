<?php

declare(strict_types=1);

use App\Services\MediaService;

/** @var array<int,array<string,mixed>> $items */
/** @var array<int,string> $tags */
$media = MediaService::make();
$fav = ($query['favorite'] ?? '') === '1';
?>
<div class="space-y-6" x-data="{ lightbox: null, list: <?= json_encode(array_values(array_map(static fn ($it) => [
    'id' => (string) $it['id'],
    'src' => $media->mediaUrl($it),
    'title' => (string) ($it['title'] ?? ''),
], $items)), JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT) ?> }">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-white">Gallery</h1>
            <p class="text-sm text-neutral-400 mt-1"><?= count($items) ?> image<?= count($items) === 1 ? '' : 's' ?></p>
        </div>
        <form method="get" action="/gallery" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="q" value="<?= e($query['q'] ?? '') ?>">
            <select name="tag" class="select w-auto" onchange="this.form.submit()" aria-label="Filter by tag">
                <option value="">Any tag</option>
                <?php foreach ($tags as $t): ?>
                <option value="<?= e($t) ?>" <?= ($query['tag'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="favorite" value="<?= $fav ? '' : '1' ?>"
                    class="btn <?= $fav ? 'btn-primary' : 'btn-secondary' ?>">Favorites</button>
            <a href="/gallery" class="btn btn-ghost">Clear</a>
        </form>
    </div>

    <?php if ($items === []): ?>
        <?php $emptyTitle = 'No images yet';
              $emptyBody = 'Upload JPG, PNG, GIF, WebP or AVIF files to see them here.';
              require dirname(__DIR__) . '/partials/empty-state.php'; ?>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <?php foreach ($items as $i => $item): ?>
            <article class="card group relative overflow-hidden" style="padding:0">
                <button type="button" class="block w-full aspect-square bg-neutral-900"
                        @click="lightbox = <?= (int) $i ?>">
                    <img src="<?= e($media->mediaUrl($item)) ?>" alt="<?= e((string) $item['title']) ?>" loading="lazy"
                         class="w-full h-full object-cover transition duration-300 group-hover:scale-[1.04]">
                </button>
                <div class="p-3">
                    <p class="text-xs text-neutral-300 truncate" title="<?= e((string) $item['title']) ?>"><?= e((string) $item['title']) ?></p>
                    <?php if (!empty($item['tags'])): ?>
                    <div class="mt-1.5 flex flex-wrap gap-1">
                        <?php foreach (array_slice($item['tags'], 0, 3) as $t): ?>
                        <a class="chip !text-[10px]" href="/gallery?tag=<?= urlencode($t) ?>"><?= e($t) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="/player?id=<?= e((string) $item['id']) ?>"
                   class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/60 border border-neutral-800 text-neutral-400 hover:text-cyan-400 opacity-0 group-hover:opacity-100 transition"
                   aria-label="Open detail page" title="Open page">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M14 4h6v6M20 4l-9 9M10 5H5a1 1 0 00-1 1v13a1 1 0 001 1h13a1 1 0 001-1v-5" stroke-linecap="round"/></svg>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Lightbox modal -->
    <div x-show="lightbox !== null" x-cloak @keydown.window="if (lightbox !== null) {
                if ($event.key === 'Escape') lightbox = null;
                if ($event.key === 'ArrowRight' && lightbox < list.length - 1) lightbox++;
                if ($event.key === 'ArrowLeft' && lightbox > 0) lightbox--; }"
         class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-4"
         @click.self="lightbox = null">
        <template x-if="lightbox !== null && list[lightbox]">
            <div class="max-w-5xl w-full">
                <img :src="list[lightbox].src" :alt="list[lightbox].title"
                     class="max-h-[80vh] w-auto mx-auto rounded-lg object-contain">
                <div class="mt-4 flex items-center justify-center gap-3">
                    <button class="btn btn-secondary" :disabled="lightbox <= 0" @click="lightbox--">← Prev</button>
                    <span class="text-sm text-neutral-300 min-w-0 truncate max-w-xs" x-text="list[lightbox]?.title"></span>
                    <button class="btn btn-secondary" :disabled="lightbox >= list.length - 1" @click="lightbox++">Next →</button>
                </div>
                <div class="mt-2 text-center">
                    <a class="text-sm text-cyan-400 hover:underline" :href="'/player?id=' + (list[lightbox]?.id ?? '')">Open detail page</a>
                </div>
            </div>
        </template>
        <button class="absolute top-4 right-4 btn-icon text-white" @click="lightbox = null" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
        </button>
    </div>
</div>
