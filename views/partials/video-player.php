<?php
declare(strict_types=1);
$safeItem = json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$safeNext = json_encode($nextItem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$contextQuery = ($queueContext ?? '') !== '' ? '&tag=' . rawurlencode((string)$queueContext) : '';
?>
<section x-ref="player" class="video-player relative overflow-hidden rounded-lg border border-neutral-800 bg-black"
         x-data='videoControls(<?= e($safeItem ?: '{}') ?>,<?= e($safeNext ?: 'null') ?>,<?= e($contextQuery) ?>)'>
    <div class="video-stage relative aspect-video bg-black">
        <video x-ref="video" class="video-screen h-full w-full" preload="metadata" playsinline
               src="<?= e($item['url']) ?>"
               @timeupdate="syncTime()" @loadedmetadata="syncTime()" @play="playing=true" @pause="playing=false" @ended="onEnded()"></video>
        <button x-cloak x-show="!playing && !showNext" class="video-center-play absolute inset-0 m-auto grid h-16 w-16 place-items-center rounded-full" @click="togglePlay()" aria-label="Play">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>
        </button>
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
        <div class="video-controls" x-show="!showNext">
            <input class="video-seek" type="range" min="0" :max="duration || 0" step="0.1" x-model="currentTime" @input="seek()" aria-label="Seek video">
            <div class="video-control-row">
                <div class="video-control-group">
                    <button class="video-control-button video-play-button" @click="togglePlay()" :aria-label="playing ? 'Pause' : 'Play'" :title="playing ? 'Pause' : 'Play'">
                        <svg x-show="!playing" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>
                        <svg x-cloak x-show="playing" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h4v14H7zm6 0h4v14h-4z" fill="currentColor"/></svg>
                    </button>
                    <button class="video-control-button video-skip-button" @click="skip(-10)" aria-label="Back 10 seconds" title="Back 10 seconds"><span aria-hidden="true">−10</span></button>
                    <button class="video-control-button video-skip-button" @click="skip(10)" aria-label="Forward 10 seconds" title="Forward 10 seconds"><span aria-hidden="true">+10</span></button>
                    <span class="video-time"><span x-text="timeLabel(currentTime)"></span><span class="video-time-divider">/</span><span x-text="timeLabel(duration)"></span></span>
                </div>
                <div class="video-control-group">
                    <button class="video-control-button video-volume-button" @click="toggleMute()" :aria-label="volume > 0 ? 'Mute' : 'Unmute'" :title="volume > 0 ? 'Mute' : 'Unmute'">
                        <svg x-show="volume > 0" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4zm12.5 3a4.5 4.5 0 0 0-2.5-4.03v8.05A4.5 4.5 0 0 0 16.5 12zm-2.5-9v2.06a7 7 0 0 1 0 13.88V21a9 9 0 0 0 0-18z" fill="currentColor"/></svg>
                        <svg x-cloak x-show="volume === 0" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4zm11.59 3 2.12 2.12-1.41 1.41L14.17 13.4l-2.12 2.12-1.41-1.41L12.76 12l-2.12-2.12 1.41-1.41 2.12 2.12 2.12-2.12 1.41 1.41L15.59 12z" fill="currentColor"/></svg>
                    </button>
                    <input class="video-volume" type="range" min="0" max="1" step="0.05" x-model.number="volume" aria-label="Volume">
                    <button class="video-control-button video-settings-button" @click="$dispatch('open-media-settings')" aria-label="Media settings" title="Media settings">Settings</button>
                    <button class="video-control-button video-fullscreen-button" @click="fullscreen()" aria-label="Fullscreen" title="Fullscreen">⛶</button>
                </div>
            </div>
        </div>
    </div>
</section>
