<?php
declare(strict_types=1);
?>
<section x-data="playlistManager()">
    <div class="mb-7"><p class="eyebrow">Browse by tags</p><h1 class="page-title">Playlists</h1><p class="mt-2 text-sm text-neutral-400">A playlist includes media that matches any of its saved tags.</p></div>
    <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
        <aside class="space-y-5">
            <form class="panel space-y-4 p-5" @submit.prevent="createPlaylist($event.currentTarget)">
                <h2 class="font-semibold">Create a playlist</h2>
                <label class="block text-sm text-neutral-300">Name<input class="input mt-2 w-full" name="name" maxlength="80" required placeholder="Weekend finds"></label>
                <label class="block text-sm text-neutral-300">Tags <span class="text-neutral-500">(comma separated)</span><input class="input mt-2 w-full" name="tags" required placeholder="travel, favorites"></label>
                <button class="btn btn-primary w-full" type="submit">Save playlist</button>
            </form>
            <div class="panel p-3">
                <?php if ($playlists === []): ?><p class="p-3 text-sm text-neutral-400">Your saved playlists will appear here.</p><?php endif; ?>
                <?php foreach ($playlists as $playlist): ?>
                    <div class="flex items-center gap-2">
                        <a class="nav-item min-w-0 flex-1 <?= ($selectedPlaylist['id'] ?? '') === $playlist['id'] ? 'nav-item-active' : '' ?>" href="/playlists?id=<?= rawurlencode($playlist['id']) ?>">
                            <span class="truncate"><?= e($playlist['name']) ?></span><span class="ml-auto text-xs text-neutral-400"><?= count($playlist['tags'] ?? []) ?></span>
                        </a>
                        <button class="icon-button text-neutral-400 hover:text-cyan-400" aria-label="Delete playlist" @click="deletePlaylist('<?= e($playlist['id']) ?>')">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
        <div>
            <?php if ($selectedPlaylist !== null): ?>
                <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div><p class="eyebrow">Playlist</p><h2 class="text-2xl font-semibold"><?= e($selectedPlaylist['name']) ?></h2>
                        <div class="mt-3 flex flex-wrap gap-2"><?php foreach ($selectedPlaylist['tags'] as $tag): ?><span class="tag-chip"><?= e($tag) ?></span><?php endforeach; ?></div>
                    </div>
                    <?php $firstVideo = null; foreach ($items as $candidate) { if ($candidate['type'] === 'video') { $firstVideo = $candidate; break; } } ?>
                    <?php if ($firstVideo !== null): ?><a class="btn btn-primary" href="/player?id=<?= rawurlencode($firstVideo['id']) ?>&type=video&playlist=<?= rawurlencode($selectedPlaylist['id']) ?>">Play playlist</a><?php endif; ?>
                </div>
                <?php if ($items === []): ?><div class="empty-state"><h3 class="font-semibold">No matching media yet</h3><p class="mt-2 text-sm text-neutral-400">Tag media with one of the playlist tags to include it.</p></div>
                <?php else: ?><div class="media-grid"><?php $mediaContext = 'playlist=' . rawurlencode($selectedPlaylist['id']); foreach ($items as $item): require __DIR__ . '/../partials/media-card.php'; endforeach; unset($mediaContext); ?></div><?php endif; ?>
            <?php else: ?>
                <div class="empty-state"><div class="empty-icon">≡</div><h2 class="mt-4 text-lg font-semibold">Choose a playlist</h2><p class="mt-2 text-sm text-neutral-400">Select a saved playlist or create one from your tags.</p></div>
            <?php endif; ?>
        </div>
    </div>
</section>
