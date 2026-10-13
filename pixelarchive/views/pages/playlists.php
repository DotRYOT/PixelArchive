<?php

declare(strict_types=1);

/** @var array<int,array<string,mixed>> $playlists */
/** @var array<int,string> $tags */
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-white">Playlists</h1>
            <p class="text-sm text-neutral-400 mt-1">A playlist is a named collection of tags. Media matching any tag appears in it.</p>
        </div>
    </div>

    <!-- Create -->
    <section class="card p-5" x-data="{ name: '', tags: [], open: false }">
        <h2 class="text-white font-semibold mb-3">New playlist</h2>
        <form @submit.prevent="
                api('/api/playlists', {name, tags}).then(r => {
                    if (r.ok) { toast('Playlist created'); location.reload(); }
                    else { toast(r.error || 'Could not create playlist', 'error'); }
                })"
              class="grid sm:grid-cols-[1fr_2fr_auto] gap-3 items-end">
            <div>
                <label class="label" for="pl-name">Name</label>
                <input id="pl-name" type="text" maxlength="80" required x-model="name" class="input" placeholder="Road trip mix">
            </div>
            <div>
                <label class="label">Tags</label>
                <div class="flex flex-wrap gap-1.5 p-2 rounded-lg border border-neutral-800 bg-neutral-900 min-h-[42px]"
                     @click="open = true">
                    <template x-for="t in tags" :key="t">
                        <button type="button" class="chip !bg-cyan-500/10 !border-cyan-500/40 !text-cyan-400" @click.stop="tags = tags.filter(x => x !== t)">
                            <span x-text="t"></span> ×
                        </button>
                    </template>
                    <span x-show="tags.length === 0" class="text-sm text-neutral-500 px-1 py-0.5">Click to pick tags…</span>
                </div>
                <div x-show="open" x-transition @click.outside="open = false"
                     class="relative z-10 -mt-1 hidden-panel card p-2 max-h-40 overflow-auto" x-cloak>
                    <?php if ($tags === []): ?>
                    <p class="text-sm text-neutral-500 p-2">No tags exist yet — add tags to media first.</p>
                    <?php endif; ?>
                    <?php foreach ($tags as $t): ?>
                    <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-neutral-900 cursor-pointer text-sm text-neutral-200">
                        <input type="checkbox" class="accent-cyan-500" value="<?= e($t) ?>" @change="$event.target.checked ? tags.push('<?= e($t) ?>') : tags = tags.filter(x => x !== '<?= e($t) ?>')">
                        <?= e($t) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Create</button>
        </form>
    </section>

    <!-- List -->
    <?php if ($playlists === []): ?>
        <?php $emptyTitle = 'No playlists yet';
              $emptyBody  = 'Create a playlist above by naming it and picking one or more tags.';
              require dirname(__DIR__) . '/partials/empty-state.php'; ?>
    <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($playlists as $pl): ?>
            <article class="card p-4 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-white font-semibold truncate"><?= e((string) $pl['name']) ?></h3>
                    <button class="btn-icon text-neutral-500 hover:text-red-400 shrink-0" aria-label="Delete playlist"
                            @click="confirmDelete(<?= json_encode((string) $pl['id'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 'playlist')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4"><path d="M4 7h16M9 7V5h6v2m-8 0l1 13h8l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ((array) ($pl['tags'] ?? []) as $t): ?>
                    <a class="chip" href="/?tag=<?= e(rawurlencode((string) $t)) ?>"><?= e((string) $t) ?></a>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-neutral-400"><?= (int) ($pl['video_count'] ?? 0) ?> video<?= (int) ($pl['video_count'] ?? 1) === 1 ? '' : 's' ?> match</p>
                <div class="mt-auto flex gap-2">
                    <a href="/?playlist=<?= e((string) $pl['id']) ?>" class="btn btn-secondary btn-sm flex-1">Browse</a>
                    <?php if (!empty($pl['__first'])): ?>
                    <a href="/player?id=<?= e((string) $pl['__first']) ?>&playlist=<?= e((string) $pl['id']) ?>"
                       class="btn btn-primary btn-sm flex-1">Play</a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
