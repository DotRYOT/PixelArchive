<?php

declare(strict_types=1);

use App\Utils\Config;

/**
 * Base layout — defines the ENTIRE design system.
 * All pages render inside $content. Component classes (.btn, .card, .input,
 * .chip, etc.) are declared once here and reused everywhere via app.css.
 */
$accent = Config::getString('accent', 'cyan');
$openMediaTab = Config::getBool('openMediaTab', false);

// Single accent mapping: text token + solid token (Tailwind CDN JIT).
$accentText  = ['cyan' => 'text-cyan-400',  'emerald' => 'text-emerald-400',  'amber' => 'text-amber-400'][$accent]  ?? 'text-cyan-400';
$accentSolid = ['cyan' => 'bg-cyan-500',    'emerald' => 'bg-emerald-500',    'amber' => 'bg-amber-500'][$accent]    ?? 'bg-cyan-500';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'PixelArchive') ?> · PixelArchive</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect width='24' height='24' rx='5' fill='%23000'/><path d='M8 6v12l9-6z' fill='%2322d3ee'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { ink: '#000000', panel: '#0a0a0a' },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                }
            }
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="h-full bg-black text-neutral-100 antialiased"
      data-open-media-tab="<?= $openMediaTab ? '1' : '0' ?>"
      x-data="appRoot()">

    <div class="flex min-h-screen">
        <?php require dirname(__DIR__) . '/partials/sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-w-0">
            <?php require dirname(__DIR__) . '/partials/header.php'; ?>

            <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0">
                <?= $content ?>
            </main>

            <footer class="px-6 py-4 border-t border-neutral-800 text-xs text-neutral-500 flex items-center justify-between">
                <span>PixelArchive — local-first library</span>
                <span id="toast-anchor" class="<?= $accentText ?>"></span>
            </footer>
        </div>
    </div>

    <!-- Global toast -->
    <div x-data="toast()" @toast.window="show($event.detail)"
         x-show="visible" x-transition.opacity.duration.300ms
         class="fixed bottom-6 right-6 z-50 max-w-sm px-4 py-3 rounded-lg border bg-neutral-950 shadow-xl"
         :class="kind === 'error' ? 'border-red-500/50 text-red-300' : 'border-neutral-800 <?= $accentText ?>' "
         x-text="message" x-cloak></div>

    <script src="/assets/app.js"></script>
</body>
</html>
