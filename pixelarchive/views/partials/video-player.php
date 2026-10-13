<?php

declare(strict_types=1);

use App\Services\MediaService;

/**
 * Custom-styled HTML5 video player with control bar, keyboard shortcuts,
 * trim panel and "Up next" autoplay overlay (Alpine).
 * Expects: $item (normalized), $queue (array), $index (int), $playlistId, $tag
 */
$media = MediaService::make();
$src   = $media->mediaUrl($item);
$id    = (string) $item['id'];
$duration = (float) ($item['duration'] ?? 0);

$nextItems = [];
for ($i = $index + 1; $i < count($queue); $i++) {
    $n = $queue[$i];
    $nextItems[] = [
        'id'    => (string) $n['id'],
        'title' => (string) ($n['title'] ?? ''),
        'thumb' => $media->thumbnailUrl($n + ['type' => 'video']),
    ];
}
$ctxQuery = http_build_query(array_filter([
    'playlist' => $playlistId ?? '',
    'tag'      => $tag ?? '',
]));
?>
<div x-data="player(<?= json_encode([
        'id' => $id,
        'src' => $src,
        'duration' => $duration,
        'next' => $nextItems,
        'ctx' => $ctxQuery,
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"
     @keydown.window="if (!$el.contains(document.activeElement) && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) handleKey($event)"
     class="select-none">

    <!-- Video stage -->
    <div class="relative rounded-lg overflow-hidden border border-neutral-800 bg-black aspect-video group"
         @mousemove.throttle.200ms="wakeControls()" @mouseleave="if (playing) controlsVisible = false">

        <video x-ref="video" :src="src" preload="metadata" playsinline
               class="w-full h-full object-contain bg-black"
               @click="toggle()" @timeupdate="onTime()" @loadedmetadata="onMeta()"
               @ended="videoEnded()" @play="playing = true" @pause="playing = false"
               @volumechange.sync="vol = $event.target.volume * 100"></video>

        <!-- Center play badge when paused -->
        <div x-show="!playing && !upNext" x-transition.opacity class="absolute inset-0 grid place-items-center pointer-events-none">
            <span class="w-16 h-16 rounded-full bg-cyan-500/90 text-black grid place-items-center shadow-xl">
                <svg viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7 translate-x-[2px]"><path d="M8 5v14l11-7z"/></svg>
            </span>
        </div>

        <!-- Up next overlay -->
        <div x-show="upNext" x-cloak x-transition.opacity
             class="absolute inset-0 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="card w-full max-w-md p-4 flex gap-4 items-center" x-show="next()">
                <template x-if="next()">
                    <div class="flex gap-4 items-center w-full">
                        <img :src="next().thumb" class="w-32 aspect-video object-cover rounded-lg border border-neutral-800" alt="">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs uppercase tracking-wider text-cyan-400 mb-1">Up next in <span x-text="countdown"></span>s</p>
                            <p class="text-white font-medium truncate" x-text="next().title"></p>
                            <div class="mt-3 flex gap-2">
                                <button class="btn btn-primary btn-sm" @click="goNext()">Play now</button>
                                <button class="btn btn-secondary btn-sm" @click="cancelUpNext()">Cancel</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <p x-show="!next()" class="text-neutral-400">Queue finished.</p>
        </div>

        <!-- Control bar -->
        <div class="absolute bottom-0 inset-x-0 transition-opacity duration-200"
             :class="controlsVisible || !playing ? 'opacity-100' : 'opacity-0'"
             style="background: linear-gradient(to top, rgba(0,0,0,.9), transparent)">
            <!-- Seek -->
            <div class="px-3 pt-2">
                <input type="range" min="0" step="0.1" :max="duration || 0" x-model.number="seekPos"
                       @input="scrub()" aria-label="Seek"
                       class="seek w-full">
            </div>
            <div class="flex items-center gap-1 px-2 pb-2 text-white">
                <button class="btn-icon" @click="toggle()" :aria-label="playing ? 'Pause' : 'Play'">
                    <svg x-show="playing" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>
                    <svg x-show="!playing" x-cloak viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M8 5v14l11-7z"/></svg>
                </button>
                <button class="btn-icon" @click="skip(-5)" title="Back 5s (←)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M11 7L6 12l5 5M6 12h12a4 4 0 010 8h-2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button class="btn-icon" @click="skip(5)" title="Forward 5s (→)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 rotate-180"><path d="M11 7L6 12l5 5M6 12h12a4 4 0 010 8h-2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <span class="text-xs tabular-nums text-neutral-100 px-1">
                    <span x-text="fmt(current)"></span> <span class="text-neutral-500">/ <span x-text="fmt(duration)"></span></span>
                </span>

                <div class="ml-auto flex items-center gap-1">
                    <div class="flex items-center gap-1 group/vol">
                        <button class="btn-icon" @click="toggleMute()" :aria-label="muted ? 'Unmute' : 'Mute'">
                            <svg x-show="muted || vol === 0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M11 5L6 9H3v6h3l5 4V5zM16 9l5 6M21 9l-5 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg x-show="!muted && vol > 0" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M11 5L6 9H3v6h3l5 4V5zM15.5 8.5a5 5 0 010 7M18 6a8.5 8.5 0 010 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <input type="range" min="0" max="100" x-model.number="vol" @input="setVolume()"
                               class="volume w-0 group-hover/vol:w-20 transition-all" aria-label="Volume">
                    </div>
                    <button class="btn-icon" @click="settingsOpen = !settingsOpen" title="Trim & settings" aria-label="Settings">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                    <button class="btn-icon" @click="fullscreen()" title="Fullscreen (F)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Trim / settings panel -->
    <div x-show="settingsOpen" x-cloak x-transition.opacity class="card mt-4 p-4 sm:p-5" id="trim-panel">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-white font-semibold">Remove a section</h3>
            <button class="btn-icon" @click="settingsOpen = false" aria-label="Close panel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
            </button>
        </div>
        <p class="text-sm text-neutral-400 mb-4">
            The section between start and end is cut out of the file permanently. Accepts seconds or HH:MM:SS.
        </p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="label" for="trim-start">Start</label>
                <input id="trim-start" type="text" class="input" placeholder="e.g. 90 or 00:01:30" x-model="trimStart">
            </div>
            <div>
                <label class="label" for="trim-end">End</label>
                <input id="trim-end" type="text" class="input" placeholder="e.g. 240 or 00:04:00" x-model="trimEnd">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button class="btn btn-secondary btn-sm" @click="trimStart = fmt(current)">Use current time as start</button>
            <button class="btn btn-danger btn-sm" :disabled="trimming" @click="runTrim()">
                <span x-show="!trimming">Cut section</span>
                <span x-show="trimming" x-cloak>Cutting… this can take a while</span>
            </button>
        </div>
        <p class="text-sm mt-3" :class="trimError ? 'text-red-400' : 'text-cyan-400'"
           x-show="trimMsg || trimError" x-text="trimError || trimMsg"></p>
    </div>
</div>
