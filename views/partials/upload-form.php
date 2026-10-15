<?php
declare(strict_types=1);
?>
<section class="grid gap-6 xl:grid-cols-2">
    <form class="panel p-6" x-data="uploadManager()" @submit.prevent="uploadLocal($event.currentTarget)">
        <div class="mb-5">
            <p class="eyebrow">Local files</p>
            <h2 class="mt-2 text-xl font-semibold">Upload to your library</h2>
            <p class="mt-1 text-sm text-neutral-400">Choose videos and images together. Up to <?= e((string)$maxFiles) ?> files per request.</p>
        </div>
        <label for="upload-files" class="dropzone block cursor-pointer rounded-lg border border-dashed border-neutral-700 p-8 text-center transition hover:border-cyan-500">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-neutral-900 text-2xl text-cyan-400">↑</span>
            <span class="mt-4 block font-medium">Select media files</span>
            <span class="mt-1 block text-sm text-neutral-400">Video and image formats supported by the server</span>
            <input id="upload-files" class="sr-only" type="file" name="files[]" multiple accept="video/*,image/*" required>
            <span class="mt-4 block text-xs text-neutral-500" x-text="selected"></span>
        </label>
        <div class="mt-5 flex items-center justify-between gap-4">
            <p class="text-sm text-neutral-400" x-text="message"></p>
            <button class="btn btn-primary" type="submit" :disabled="busy"><span x-text="busy ? 'Uploading…' : 'Upload files'"></span></button>
        </div>
    </form>
    <form class="panel p-6" x-data="downloadManager()" @submit.prevent="downloadUrl()">
        <div class="mb-5">
            <p class="eyebrow">Online source</p>
            <h2 class="mt-2 text-xl font-semibold">Download a video</h2>
            <p class="mt-1 text-sm text-neutral-400">yt-dlp saves the result directly into this library.</p>
        </div>
        <label for="download-url" class="mb-2 block text-sm font-medium text-neutral-200">Video URL</label>
        <input id="download-url" class="input w-full" type="url" x-model="url" placeholder="https://…" required maxlength="2048">
        <div class="mt-5 flex items-center justify-between gap-4">
            <p class="text-sm text-neutral-400" x-text="message"></p>
            <button class="btn btn-primary" type="submit" :disabled="busy"><span x-text="busy ? 'Downloading…' : 'Download video'"></span></button>
        </div>
    </form>
</section>
