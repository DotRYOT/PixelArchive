<?php

declare(strict_types=1);

/** @var array<int,array<string,mixed>> $items */
/** @var array<int,string> $tags */
/** @var array<string,string> $query */
$fav = ($query['favorite'] ?? '') === '1';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-white">Library</h1>
            <p class="text-sm text-neutral-400 mt-1"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></p>
        </div>

        <!-- Filters -->
        <form method="get" action="/" class="flex flex-wrap items-center gap-2" id="filter-tag">
            <input type="hidden" name="q" value="<?= e($query['q']) ?>">
            <select name="type" class="select w-auto" onchange="this.form.submit()" aria-label="Filter by type">
                <?php foreach (['all' => 'All media', 'video' => 'Videos', 'image' => 'Images'] as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= ($query['type'] ?: 'all') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tag" class="select w-auto" onchange="this.form.submit()" aria-label="Filter by tag">
                <option value="">Any tag</option>
                <?php foreach ($tags as $t): ?>
                <option value="<?= e($t) ?>" <?= $query['tag'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="favorite" value="<?= $fav ? '' : '1' ?>"
                    class="btn <?= $fav ? 'btn-primary' : 'btn-secondary' ?>" aria-pressed="<?= $fav ? 'true' : 'false' ?>">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="<?= $fav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 21s-7.5-4.6-9.3-9A5.6 5.6 0 0 1 12 6.5 5.6 5.6 0 0 1 21.3 12c-1.8 4.4-9.3 9-9.3 9z"/>
                </svg>
                Favorites
            </button>
            <?php if ($query['q'] !== '' || $query['tag'] !== '' || ($query['type'] !== 'all' && $query['type'] !== '') || $fav): ?>
            <a href="/" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($items === []): ?>
        <?php require dirname(__DIR__) . '/partials/empty-state.php'; ?>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <?php foreach ($items as $item): ?>
                <?php require dirname(__DIR__, 2) . '/views/partials/media-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
