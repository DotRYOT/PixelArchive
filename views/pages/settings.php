<?php
declare(strict_types=1);
$settingsJson = json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<section class="mx-auto max-w-3xl" x-data="settingsForm(<?= e($settingsJson ?: '{}') ?>)">
    <div class="mb-7"><p class="eyebrow">Application preferences</p><h1 class="page-title">Settings</h1><p class="mt-2 text-sm text-neutral-400">These values are stored in the local application configuration.</p></div>
    <form class="panel space-y-6 p-6 sm:p-8" @submit.prevent="save()">
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-medium">Thumbnail frame time <span class="text-neutral-400">(seconds)</span><input class="input mt-2 w-full" type="number" min="0" max="3600" step="1" x-model.number="settings.frameTime" required></label>
            <label class="block text-sm font-medium">Maximum files per upload<input class="input mt-2 w-full" type="number" min="1" max="1000" step="1" x-model.number="settings.maxFiles" required></label>
            <label class="block text-sm font-medium">Thumbnail width<input class="input mt-2 w-full" type="number" min="64" max="4096" step="1" x-model.number="settings.thumbWidth" required></label>
            <label class="block text-sm font-medium">Thumbnail height<input class="input mt-2 w-full" type="number" min="64" max="4096" step="1" x-model.number="settings.thumbHeight" required></label>
            <label class="block text-sm font-medium">Downloaded video extension <span class="text-neutral-400">(mp4, webm, mkv)</span><input class="input mt-2 w-full" pattern="(mp4|webm|mkv)" maxlength="4" x-model="settings.videoExtension" required></label>
            <label class="block text-sm font-medium">Accent color<select class="input mt-2 w-full" x-model="settings.accent"><option value="cyan">Cyan</option></select></label>
        </div>
        <label class="flex items-center gap-3 rounded-lg border border-neutral-800 bg-black p-4 text-sm">
            <input class="h-4 w-4 accent-cyan-400" type="checkbox" x-model="settings.openMediaTab">
            <span><span class="block font-medium">Open media in a new tab</span><span class="mt-1 block text-neutral-400">Applies to media cards.</span></span>
        </label>
        <div class="flex items-center justify-between gap-4 border-t border-neutral-800 pt-5"><p class="text-sm text-neutral-400">Storage location is managed by the application and is not editable here.</p><button class="btn btn-primary" type="submit" :disabled="busy" x-text="busy ? 'Saving…' : 'Save settings'"></button></div>
    </form>
</section>
