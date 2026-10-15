<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= e($pageTitle ?? 'Pixl Archive') ?> · Pixl Archive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/app.css">
    <script defer src="/assets/app.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-black text-neutral-100 antialiased" x-data="{sidebarOpen:false}">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="min-h-screen lg:pl-64">
        <?php require __DIR__ . '/../partials/header.php'; ?>
        <main class="mx-auto max-w-[1600px] px-4 pb-16 pt-7 sm:px-6 lg:px-10">
            <div id="toast" class="toast" role="status" aria-live="polite"></div>
            <?= $content ?>
        </main>
    </div>
</body>
</html>
