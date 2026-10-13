<?php

declare(strict_types=1);

/** Upload page — thin wrapper around the shared upload-form partial. */
$maxFiles = $maxFiles ?? 100;
?>
<div class="space-y-6 max-w-4xl mx-auto">
    <div>
        <h1 class="text-2xl font-semibold text-white">Add media</h1>
        <p class="text-sm text-neutral-400 mt-1">Upload local files or pull a video from the web with yt-dlp.</p>
    </div>
    <?php require dirname(__DIR__) . '/partials/upload-form.php'; ?>
</div>
