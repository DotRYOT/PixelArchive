<?php
declare(strict_types=1);
$mediaContext = $mediaContext ?? '';
$openUrl = '/player?id=' . rawurlencode((string)$item['id']) . '&type=' . rawurlencode((string)$item['type']);
if ($mediaContext !== '') {
    $openUrl .= '&' . $mediaContext;
}
?>
<article class="card group overflow-hidden">
    <a class="relative block aspect-video overflow-hidden bg-neutral-950" href="<?= e($openUrl) ?>" aria-label="Open <?= e($item['title']) ?>" <?= !empty($openMediaTab) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
        <img class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
             src="<?= e($item['thumbnail_url']) ?>" alt="" loading="lazy">
        <?php if ($item['type'] === 'video'): ?>
            <span class="absolute bottom-3 right-3 rounded bg-black/80 px-2 py-1 text-xs font-medium text-white"><?= e(format_duration((float)($item['duration'] ?? 0))) ?></span>
        <?php else: ?>
            <span class="absolute bottom-3 right-3 rounded bg-black/80 px-2 py-1 text-xs font-medium text-white">IMAGE</span>
        <?php endif; ?>
        <span class="absolute inset-0 grid place-items-center bg-black/0 text-3xl text-white opacity-0 transition group-hover:bg-black/20 group-hover:opacity-100" aria-hidden="true">▶</span>
    </a>
    <div class="p-4">
        <div class="flex items-start justify-between gap-3">
            <a href="<?= e($openUrl) ?>" class="line-clamp-2 min-w-0 font-medium text-neutral-100 hover:text-cyan-400" <?= !empty($openMediaTab) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($item['title']) ?></a>
            <button type="button" class="icon-button shrink-0 <?= !empty($item['favorite']) ? 'text-cyan-400' : 'text-neutral-400' ?>"
                    aria-label="<?= !empty($item['favorite']) ? 'Remove from favorites' : 'Add to favorites' ?>"
                    onclick="toggleFavorite('<?= e($item['id']) ?>','<?= e($item['type']) ?>',this)"><?= !empty($item['favorite']) ? '♥' : '♡' ?></button>
        </div>
        <?php if (!empty($item['tags'])): ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ($item['tags'] as $tag): ?>
                    <a class="tag-chip" href="/?tag=<?= rawurlencode($tag) ?>"><?= e($tag) ?></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-3 text-xs text-neutral-500">No tags</div>
        <?php endif; ?>
    </div>
</article>
