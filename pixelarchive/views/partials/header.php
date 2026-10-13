<?php

declare(strict_types=1);

use App\Utils\Config;

$accent = Config::getString('accent', 'cyan');
$accentText = ['cyan' => 'text-cyan-400', 'emerald' => 'text-emerald-400', 'amber' => 'text-amber-400'][$accent] ?? 'text-cyan-400';
$q = (string) ($_GET['q'] ?? '');
?>
<header class="sticky top-0 z-20 border-b border-neutral-800 bg-black/90 backdrop-blur px-4 sm:px-6 py-3 flex items-center gap-3">
    <!-- Mobile menu button -->
    <button @click="$store.ui.toggleSidebar()" type="button"
            class="lg:hidden btn-icon" aria-label="Open navigation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5">
            <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
        </svg>
    </button>

    <!-- Search submits to the current page so filters stay contextual -->
    <form method="get" action="<?= e(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') ?>"
          class="flex-1 max-w-xl relative" role="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 pointer-events-none">
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
        </svg>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search titles and tags…"
               class="input pl-9" aria-label="Search media">
    </form>

    <div class="ml-auto flex items-center gap-2">
        <a href="/upload" class="btn btn-primary hidden sm:inline-flex">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
            </svg>
            Upload
        </a>
        <a href="/upload" class="btn btn-primary sm:hidden" aria-label="Upload">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
            </svg>
        </a>
    </div>
</header>
