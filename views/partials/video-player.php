<?php
declare(strict_types=1);
$safeItem = json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$safeNext = json_encode($nextItem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$contextQuery = ($queueContext ?? '') !== '' ? '&tag=' . rawurlencode((string)$queueContext) : '';
?>
<section class="relative overflow-hidden rounded-lg border border-neutral-800 bg-black"
         x-data='videoControls(<?= e($safeItem ?: '{}') ?>,<?= e($safeNext ?: 'null') ?>,<?= e($contextQuery) ?>)'>
    <div class="relative aspect-video bg-black">
        <video x-ref="video" class="h-full w-full" preload="metadata" playsinline
               src="<?= e($item['url']) ?>"
               @timeupdate="syncTime()" @loadedmetadata="syncTime()" @play="playing=true" @pause="playing=false" @ended="onEnded()"></video>
        <button x-show="!playing && !showNext" class="absolute inset-0 m-auto grid h-16 w-16 place-items-center rounded-full bg-cyan-500 text-2xl text-black shadow-lg" @click="togglePlay()" aria-label="Play">▶</button>
        <div x-cloak x-show="showNext" class="absolute inset-0 grid place-items-center bg-black/85 p-6 text-center">
            <div class="max-w-sm">
                <p class="text-sm font-semibold uppercase tracking-widest text-cyan-400">Up next</p>
                <img class="mx-auto mt-4 aspect-video w-56 rounded-lg border border-neutral-800 object-cover" :src="next?.thumbnail_url" alt="">
                <h3 class="mt-4 text-lg font-semibold" x-text="next?.title"></h3>
                <p class="mt-1 text-sm text-neutral-400">Playing in <span class="font-semibold text-white" x-text="countdown"></span> seconds</p>
                <div class="mt-5 flex justify-center gap-3">
                    <button class="btn btn-secondary" @click="cancelNext()">Cancel</button>
                    <button class="btn btn-primary" @click="playNext()">Play now</button>
                </div>
            </div>
        </div>
    </div>
    <div class="space-y-3 border-t border-neutral-800 bg-neutral-950 p-4">
        <input class="seek-range w-full" type="range" min="0" :max="duration || 0" step="0.1" x-model="currentTime" @input="seek()">
        <div class="flex flex-wrap items-center gap-3">
            <button class="btn btn-secondary px-3" @click="togglePlay()" x-text="playing ? 'Ⅱ' : '▶'" :aria-label="playing ? 'Pause' : 'Play'"></button>
            <span class="min-w-24 font-mono text-xs text-neutral-300"><span x-text="timeLabel(currentTime)"></span> / <span x-text="timeLabel(duration)"></span></span>
            <label class="flex items-center gap-2 text-xs text-neutral-400"><span>VOL</span><input class="volume-range w-24" type="range" min="0" max="1" step="0.05" x-model="volume" @input="setVolume()"></label>
            <div class="ml-auto flex gap-2">
                <button class="btn btn-secondary" @click="$dispatch('open-media-settings')">Media settings</button>
                <button class="btn btn-secondary px-3" @click="fullscreen()" aria-label="Fullscreen">⛶</button>
            </div>
        </div>
    </div>
</section>
