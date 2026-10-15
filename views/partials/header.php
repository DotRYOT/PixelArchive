<?php
declare(strict_types=1);
?>
<header class="sticky top-0 z-30 border-b border-neutral-800 bg-black/95 backdrop-blur">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-10">
        <button type="button" class="btn btn-secondary px-3 lg:hidden" aria-label="Open navigation" @click="sidebarOpen = !sidebarOpen">☰</button>
        <form action="/" method="get" class="relative min-w-0 max-w-2xl flex-1">
            <label class="sr-only" for="global-search">Search your library</label>
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400">⌕</span>
            <input id="global-search" class="input w-full pl-9" name="q" value="<?= e(is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>" placeholder="Search titles and tags">
        </form>
        <a class="btn btn-primary shrink-0" href="/upload"><span aria-hidden="true">＋</span><span class="hidden sm:inline">Add media</span></a>
    </div>
</header>
