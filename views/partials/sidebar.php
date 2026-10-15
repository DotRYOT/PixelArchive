<?php
declare(strict_types=1);
$navItems = [
    ['home', '/', '⌂', 'Library'],
    ['gallery', '/gallery', '▧', 'Images'],
    ['playlists', '/playlists', '≡', 'Playlists'],
    ['favorites', '/?favorites=1', '♡', 'Favorites'],
];
?>
<div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-black/70 lg:hidden" @click="sidebarOpen=false"></div>
<aside class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-neutral-800 bg-black transition-transform lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
    <div class="flex h-16 items-center gap-3 border-b border-neutral-800 px-6">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-cyan-500 text-lg font-black text-black">P</div>
        <div><div class="font-semibold tracking-tight">Pixl Archive</div><div class="text-xs text-neutral-400">Your local library</div></div>
    </div>
    <nav class="flex-1 space-y-1 px-3 py-6" aria-label="Main navigation">
        <div class="px-3 pb-2 text-xs font-semibold uppercase tracking-widest text-neutral-500">Library</div>
        <?php foreach ($navItems as [$key, $href, $icon, $label]): ?>
            <a href="<?= e($href) ?>" @click="sidebarOpen=false" class="nav-item <?= ($activeNav ?? '') === $key ? 'nav-item-active' : '' ?>">
                <span class="w-6 text-center text-lg" aria-hidden="true"><?= e($icon) ?></span><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="border-t border-neutral-800 p-3">
        <a href="/settings" @click="sidebarOpen=false" class="nav-item <?= ($activeNav ?? '') === 'settings' ? 'nav-item-active' : '' ?>">
            <span class="w-6 text-center text-lg" aria-hidden="true">⚙</span><span>Settings</span>
        </a>
        <div class="px-3 pt-4 text-xs text-neutral-500">Stored on this device</div>
    </div>
</aside>
