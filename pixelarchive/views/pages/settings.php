<?php

declare(strict_types=1);

/** @var array<string,mixed> $config */
/** @var array<int,string> $accents */
?>
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-white">Settings</h1>
        <p class="text-sm text-neutral-400 mt-1">Runtime options are stored in <code class="text-cyan-400">config.php</code>. No code changes needed.</p>
    </div>

    <?php if (!empty($saved)): ?>
    <div class="card p-4 border-cyan-500/40 text-cyan-400 text-sm">Settings saved.</div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="card p-4 border-red-500/40 text-red-400 text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/settings" class="card p-5 sm:p-6 space-y-6">
        <section class="space-y-4">
            <h2 class="text-white font-semibold uppercase tracking-wider text-xs">Thumbnails</h2>
            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="label" for="frameTime">Frame offset (seconds)</label>
                    <input id="frameTime" name="frameTime" type="number" min="0" max="3600" step="1"
                           value="<?= (int) $config['frameTime'] ?>" class="input" required>
                </div>
                <div>
                    <label class="label" for="thumbWidth">Thumb width (px)</label>
                    <input id="thumbWidth" name="thumbWidth" type="number" min="64" max="3840"
                           value="<?= (int) $config['thumbWidth'] ?>" class="input" required>
                </div>
                <div>
                    <label class="label" for="thumbHeight">Thumb height (px)</label>
                    <input id="thumbHeight" name="thumbHeight" type="number" min="64" max="2160"
                           value="<?= (int) $config['thumbHeight'] ?>" class="input" required>
                </div>
            </div>
        </section>

        <hr class="border-neutral-800">

        <section class="space-y-4">
            <h2 class="text-white font-semibold uppercase tracking-wider text-xs">Media</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="videoExtension">Download video extension</label>
                    <input id="videoExtension" name="videoExtension" type="text" pattern="[a-z0-9]{2,5}"
                           maxlength="5" value="<?= e((string) $config['videoExtension']) ?>" class="input" required>
                </div>
                <div>
                    <label class="label" for="maxFiles">Max files per upload</label>
                    <input id="maxFiles" name="maxFiles" type="number" min="1" max="1000"
                           value="<?= (int) $config['maxFiles'] ?>" class="input" required>
                </div>
            </div>
            <label class="flex items-center gap-3 cursor-pointer select-none">
                <input type="checkbox" name="openMediaTab" value="1" <?= $config['openMediaTab'] ? 'checked' : '' ?>
                       class="w-4 h-4 rounded accent-cyan-500 bg-neutral-900 border-neutral-700">
                <span class="text-sm text-neutral-200">Open media in a new browser tab when clicked</span>
            </label>
        </section>

        <hr class="border-neutral-800">

        <section class="space-y-4">
            <h2 class="text-white font-semibold uppercase tracking-wider text-xs">Appearance</h2>
            <div>
                <label class="label" for="accent">Accent color</label>
                <select id="accent" name="accent" class="select">
                    <?php foreach ($accents as $a): ?>
                    <option value="<?= e($a) ?>" <?= $config['accent'] === $a ? 'selected' : '' ?>><?= ucfirst($a) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-neutral-500 mt-1.5">One accent only — it drives nav highlights, buttons, focus rings and progress bars.</p>
            </div>
        </section>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Save settings</button>
            <a href="/settings" class="btn btn-ghost">Reset form</a>
        </div>
    </form>

    <!-- TODO (optional extension): add per-tool path overrides (ffmpeg/ffprobe/yt-dlp) here once the SettingsController whitelists them. -->
</div>
