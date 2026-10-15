<?php
declare(strict_types=1);
?>
<?php if ($item === null): ?>
    <div class="empty-state"><div class="empty-icon">?</div><h1 class="mt-4 text-xl font-semibold">Media not found</h1><p class="mt-2 text-sm text-neutral-400">This item may have been removed from the archive.</p><a class="btn btn-primary mt-5" href="/">Back to library</a></div>
<?php else: ?>
    <?php
    $safeItem = json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    ?>
    <section class="mx-auto max-w-6xl" x-data="mediaSettings(<?= e($safeItem ?: '{}') ?>)" @open-media-settings="openSettings=true">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div><p class="eyebrow"><?= $item['type'] === 'video' ? 'Video' : 'Image' ?> · <?= e(date('M j, Y', strtotime((string)$item['created_at']))) ?></p><h1 class="page-title mt-1"><?= e($item['title']) ?></h1></div>
            <div class="flex gap-2">
                <button class="btn btn-secondary" @click="favorite()"><span x-text="item.favorite ? '♥ Favorited' : '♡ Favorite'"></span></button>
                <button class="btn btn-secondary" @click="openSettings=true">Settings</button>
                <?php if ($item['type'] === 'video'): ?><a class="btn btn-secondary" href="<?= e($item['url']) ?>" download="<?= e($item['filename']) ?>">Download</a><?php endif; ?>
            </div>
        </div>
        <?php if ($item['type'] === 'video'): ?>
            <?php require __DIR__ . '/../partials/video-player.php'; ?>
            <div class="mt-5 flex flex-wrap items-center gap-2">
                <?php foreach ($item['tags'] as $tag): ?><a class="tag-chip" href="/?tag=<?= rawurlencode($tag) ?>"><?= e($tag) ?></a><?php endforeach; ?>
                <?php if ($item['tags'] === []): ?><span class="text-sm text-neutral-500">No tags</span><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-hidden rounded-lg border border-neutral-800 bg-neutral-950">
                <div class="flex min-h-64 items-center justify-center bg-black p-2 sm:p-6"><img class="max-h-[75vh] max-w-full object-contain" src="<?= e($item['url']) ?>" alt="<?= e($item['title']) ?>"></div>
                <div class="flex items-center justify-between border-t border-neutral-800 p-4">
                    <?php if ($previousImage !== null): ?><a class="btn btn-secondary" href="/player?id=<?= rawurlencode($previousImage['id']) ?>&type=image">← Previous</a><?php else: ?><span></span><?php endif; ?>
                    <span class="text-sm text-neutral-400"><?= e((string)$item['width']) ?> × <?= e((string)$item['height']) ?></span>
                    <?php if ($nextImage !== null): ?><a class="btn btn-secondary" href="/player?id=<?= rawurlencode($nextImage['id']) ?>&type=image">Next →</a><?php else: ?><span></span><?php endif; ?>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <?php foreach ($item['tags'] as $tag): ?><a class="tag-chip" href="/?tag=<?= rawurlencode($tag) ?>"><?= e($tag) ?></a><?php endforeach; ?>
                <?php if ($item['tags'] === []): ?><span class="text-sm text-neutral-500">No tags</span><?php endif; ?>
            </div>
        <?php endif; ?>

        <div x-cloak x-show="openSettings" x-transition class="modal-backdrop" @keydown.escape.window="openSettings=false" @click.self="openSettings=false">
            <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="media-settings-heading">
                <div class="flex items-start justify-between gap-4"><div><p class="eyebrow">Edit archive entry</p><h2 id="media-settings-heading" class="mt-1 text-xl font-semibold">Media settings</h2></div><button class="icon-button" @click="openSettings=false" aria-label="Close">×</button></div>
                <form class="mt-6 space-y-4" @submit.prevent="save()">
                    <label class="block text-sm font-medium">Title<input class="input mt-2 w-full" x-model="title" maxlength="160" required></label>
                    <label class="block text-sm font-medium">Tags <span class="text-neutral-500">(comma separated)</span><input class="input mt-2 w-full" x-model="tags" maxlength="2048" placeholder="travel, family"></label>
                    <button class="btn btn-primary w-full" type="submit" :disabled="busy" x-text="busy ? 'Saving…' : 'Save changes'"></button>
                </form>
                <?php if ($item['type'] === 'video'): ?>
                    <form class="mt-8 space-y-4 border-t border-neutral-800 pt-6" @submit.prevent="trim()">
                        <div><h3 class="font-semibold">Remove a section</h3><p class="mt-1 text-sm text-neutral-400">The section between start and end is removed.</p></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm text-neutral-300">Start<input class="input mt-2 w-full" x-model="trimStart" placeholder="00:00:10" required></label>
                            <label class="block text-sm text-neutral-300">End<input class="input mt-2 w-full" x-model="trimEnd" placeholder="00:00:20" required></label>
                        </div>
                        <button class="btn btn-secondary w-full" type="submit" :disabled="busy" x-text="busy ? 'Processing…' : 'Remove section'"></button>
                    </form>
                <?php endif; ?>
                <div class="mt-6 border-t border-neutral-800 pt-5"><button class="btn btn-danger w-full" type="button" @click="remove()">Delete media</button></div>
            </section>
        </div>
    </section>
<?php endif; ?>
