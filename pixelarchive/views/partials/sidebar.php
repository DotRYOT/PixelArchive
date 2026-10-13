<?php

declare(strict_types=1);

use App\Utils\Config;

$accent = Config::getString('accent', 'cyan');
$accentText = ['cyan' => 'text-cyan-400', 'emerald' => 'text-emerald-400', 'amber' => 'text-amber-400'][$accent] ?? 'text-cyan-400';
$accentSolid = ['cyan' => 'bg-cyan-500', 'emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500'][$accent] ?? 'bg-cyan-500';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$nav = [
    ['/',       'Library',  'M4 6h16M4 12h16M4 18h10'],
    ['/gallery','Gallery',  'M4 5h16v14H4zM9 10a1.5 1.5 0 100 .01M5 17l4-4 3 3 4-4 3 3'],
    ['/playlists','Playlists','M4 6h8M4 12h8M4 18h8M15 8l5 3-5 3z'],
    ['/upload', 'Upload',   'M12 16V4m0 0L7 9m5-5l5 5M4 20h16'],
    ['/settings','Settings','M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 11-14 0 7 7 0 0114 0z'],
];
?>
<!-- Sidebar: fixed on desktop, off-canvas drawer on small screens -->
<div id="sidebar-overlay" @click="$store.ui.closeSidebar()" x-show="$store.ui.sidebarOpen"
     x-transition.opacity class="fixed inset-0 z-30 bg-black/70 lg:hidden" x-cloak></div>

<aside x-data x-show="true"
       :class="{ 'hidden lg:flex': !open }"
       class="fixed lg:sticky top-0 z-40 h-screen w-60 shrink-0 flex-col border-r border-neutral-800 bg-panel p-4 gap-2"
       style="background:#0a0a0a">
    <a href="/" class="flex items-center gap-3 px-2 py-3 mb-2 group">
        <span class="w-9 h-9 rounded-lg <?= $accentSolid ?> grid place-items-center">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-black" fill="currentColor"><path d="M8 6v12l9-6z"/></svg>
        </span>
        <span class="leading-tight">
            <span class="block font-semibold text-white">PixelArchive</span>
            <span class="block text-[11px] text-neutral-400">local-first library</span>
        </span>
    </a>

    <?php foreach ($nav as [$href, $label, $d]):
        $active = ($href === '/') ? ($currentPath === '/') : str_starts_with($currentPath, $href); ?>
    <a href="<?= e($href) ?>"
       class="nav-item <?= $active
            ? 'active bg-neutral-900 ' . $accentText . ' border-l-2'
            : 'text-neutral-400 hover:bg-neutral-900 hover:text-white border-l-2 border-transparent' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 shrink-0">
            <path d="<?= e($d) ?>"/>
        </svg>
        <span><?= e($label) ?></span>
    </a>
    <?php endforeach; ?>

    <div class="mt-auto pt-4 border-t border-neutral-800 text-[11px] text-neutral-500 px-2">
        <p class="mb-1 uppercase tracking-wider text-neutral-400 font-medium">Shortcuts</p>
        <p><span class="<?= $accentText ?>">Space</span> play/pause · <span class="<?= $accentText ?>">← →</span> seek 5s</p>
        <p><span class="<?= $accentText ?>">F</span> fullscreen · <span class="<?= $accentText ?>">M</span> mute</p>
    </div>
</aside>

<script>window.__sidebarActive = true;</script>
