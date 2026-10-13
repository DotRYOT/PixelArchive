<?php

declare(strict_types=1);

use App\Utils\Config;

/**
 * Upload form partial: local files + yt-dlp URL download.
 * Expects: $maxFiles (int|null)
 */
$maxFiles = $maxFiles ?? Config::getInt('maxFiles', 100);
?>
<div x-data="uploadForm(<?= (int) $maxFiles ?>)" class="grid lg:grid-cols-2 gap-6 items-start">

    <!-- Local upload -->
    <section class="card p-5">
        <h2 class="text-white font-semibold mb-1">Upload from this machine</h2>
        <p class="text-sm text-neutral-400 mb-4">
            Select videos and/or images. Up to <span class="text-cyan-400"><?= (int) $maxFiles ?></span> files per batch.
        </p>

        <form @submit.prevent="upload()" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="label">Videos</label>
                <input type="file" name="videos[]" multiple accept="video/*,.mkv,.avi,.flv,.m4v"
                       x-ref="videoInput" @change="pickVideos($event)"
                       class="input file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-800 file:text-white file:px-3 file:py-1.5 file:text-sm">
                <template x-if="videoNames.length">
                    <ul class="mt-2 space-y-1 max-h-32 overflow-auto text-sm text-neutral-300">
                        <template x-for="n in videoNames" :key="n"><li class="truncate flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 shrink-0"></span><span x-text="n"></span>
                        </li></template>
                    </ul>
                </template>
            </div>

            <div>
                <label class="label">Images</label>
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp"
                       x-ref="imageInput" @change="pickImages($event)"
                       class="input file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-800 file:text-white file:px-3 file:py-1.5 file:text-sm">
                <template x-if="imageNames.length">
                    <ul class="mt-2 space-y-1 max-h-32 overflow-auto text-sm text-neutral-300">
                        <template x-for="n in imageNames" :key="n"><li class="truncate flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 shrink-0"></span><span x-text="n"></span>
                        </li></template>
                    </ul>
                </template>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary" :disabled="busy">
                    <span x-show="!busy">Upload files</span>
                    <span x-show="busy" x-cloak>Uploading…</span>
                </button>
                <div x-show="busy" class="flex-1 h-1.5 rounded bg-neutral-800 overflow-hidden">
                    <div class="h-full bg-cyan-500 rounded animate-pulse w-2/3"></div>
                </div>
            </div>

            <p x-show="message" x-text="message" x-transition.opacity
               class="text-sm" :class="hasError ? 'text-red-400' : 'text-cyan-400'"></p>
        </form>
    </section>

    <!-- yt-dlp download -->
    <section class="card p-5">
        <h2 class="text-white font-semibold mb-1">Download with yt-dlp</h2>
        <p class="text-sm text-neutral-400 mb-4">Paste a URL; the video is fetched into your library automatically.</p>

        <form @submit.prevent="download()" class="space-y-4">
            <div>
                <label class="label" for="dl-url">Media URL</label>
                <input id="dl-url" type="url" required maxlength="2000" placeholder="https://…"
                       x-model="url" class="input">
            </div>
            <div>
                <label class="label" for="dl-title">Title (optional)</label>
                <input id="dl-title" type="text" maxlength="160" x-model="title" class="input" placeholder="Defaults to the source title">
            </div>
            <button type="submit" class="btn btn-primary" :disabled="busyDl">
                <span x-show="!busyDl">Start download</span>
                <span x-show="busyDl" x-cloak>Downloading… large files take a while</span>
            </button>
            <p x-show="dlMessage" x-text="dlMessage" x-transition.opacity
               class="text-sm" :class="dlError ? 'text-red-400' : 'text-cyan-400'"></p>
        </form>
    </section>
</div>
