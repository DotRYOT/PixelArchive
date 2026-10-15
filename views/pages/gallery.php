<?php
declare(strict_types=1);
?>
<section>
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="eyebrow">Still frames</p><h1 class="page-title">Image gallery</h1><p class="mt-2 text-sm text-neutral-400"><?= count($items) ?> images</p></div>
        <form action="/gallery" method="get" class="flex flex-wrap gap-2">
            <input class="input w-48" type="search" name="q" value="<?= e($query) ?>" placeholder="Title or tag">
            <select class="input" name="tag"><option value="">All tags</option><?php foreach ($allTags as $tag): ?><option value="<?= e($tag) ?>" <?= $tagFilter === $tag ? 'selected' : '' ?>><?= e($tag) ?></option><?php endforeach; ?></select>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
    </div>
    <?php if ($items === []): ?>
        <div class="empty-state"><div class="empty-icon">▧</div><h2 class="mt-4 text-lg font-semibold">No images found</h2><p class="mt-2 text-sm text-neutral-400">Upload images or adjust your filters.</p><a href="/upload" class="btn btn-primary mt-5">Add images</a></div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($items as $item): require __DIR__ . '/../partials/media-card.php'; endforeach; ?>
        </div>
    <?php endif; ?>
</section>
