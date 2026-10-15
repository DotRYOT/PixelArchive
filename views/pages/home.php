<?php
declare(strict_types=1);
?>
<section>
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow"><?= $favoritesOnly ? 'Saved for later' : 'Your collection' ?></p>
            <h1 class="page-title"><?= e($pageTitle) ?></h1>
            <p class="mt-2 text-sm text-neutral-400"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?> in view</p>
        </div>
        <form action="/" method="get" class="flex flex-wrap gap-2">
            <?php if ($favoritesOnly): ?><input type="hidden" name="favorites" value="1"><?php endif; ?>
            <input class="input w-44" type="search" name="q" value="<?= e($query) ?>" placeholder="Title or tag">
            <select class="input" name="type" aria-label="Media type">
                <option value="">All media</option><option value="video" <?= $typeFilter === 'video' ? 'selected' : '' ?>>Videos</option><option value="image" <?= $typeFilter === 'image' ? 'selected' : '' ?>>Images</option>
            </select>
            <select class="input" name="tag" aria-label="Filter by tag">
                <option value="">All tags</option>
                <?php foreach ($allTags as $tag): ?><option value="<?= e($tag) ?>" <?= $tagFilter === $tag ? 'selected' : '' ?>><?= e($tag) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
    </div>
    <?php if ($items === []): ?>
        <div class="empty-state">
            <div class="empty-icon">▧</div><h2 class="mt-4 text-lg font-semibold">Nothing here yet</h2>
            <p class="mt-2 text-sm text-neutral-400"><?= $favoritesOnly ? 'Favorite media will appear here.' : 'Upload a video or image to start building your local library.' ?></p>
            <?php if (!$favoritesOnly): ?><a href="/upload" class="btn btn-primary mt-5">Add your first media</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($items as $item): require __DIR__ . '/../partials/media-card.php'; endforeach; ?>
        </div>
    <?php endif; ?>
</section>
