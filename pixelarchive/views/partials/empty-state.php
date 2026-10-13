<?php

declare(strict_types=1);

/** Shared empty state component — used by home, gallery, playlists, player queue. */
$emptyTitle = $emptyTitle ?? 'Nothing here yet';
$emptyBody  = $emptyBody ?? 'Upload media or download a video to start building your hoard.';
?>
<div class="card p-12 text-center">
    <div class="mx-auto w-14 h-14 rounded-lg bg-neutral-900 border border-neutral-800 grid place-items-center mb-4">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
             class="w-7 h-7 text-cyan-400">
            <path d="M4 6h16v12H4z" stroke-linejoin="round"/><path d="M9 10.5a1 1 0 11-.001-2.001A1 1 0 019 10.5zM5.5 16l3.5-3.5 2.5 2.5 3-3L18.5 16"/>
        </svg>
    </div>
    <h3 class="text-white font-semibold text-lg"><?= e($emptyTitle) ?></h3>
    <p class="text-neutral-400 text-sm mt-1 max-w-md mx-auto"><?= e($emptyBody) ?></p>
    <div class="mt-5 flex justify-center gap-3">
        <a href="/upload" class="btn btn-primary">Upload media</a>
        <a href="/settings" class="btn btn-secondary">Settings</a>
    </div>
</div>
