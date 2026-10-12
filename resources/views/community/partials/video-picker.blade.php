{{--
    One video for a post or a top-level answer (resources/js/videos.js), beside the photo button in
    partials/photo-picker: checked in the browser (≤ 50 MB, ≤ 1 minute), uploaded in pieces as soon as
    it's picked (progress, cancel, continues after a dropped connection); the form sends its id as
    `video`. Edit forms (`fromItem`) start with the item's video from its <template data-raw-video>.
    Shown only while the server can convert videos (`available`), or when the item already has one.
    The wrapper is `contents`, so the button sits in the photo button's row and the tile above it.
--}}
<div data-video class="contents"
    x-data="videoPicker({{ ($fromItem ?? false) ? "JSON.parse(\$el.closest('article')?.querySelector('template[data-raw-video]')?.content.textContent || 'null')" : 'null' }}, @js($available))">
    <div x-show="video" x-cloak class="video-tile order-first" :class="video?.error && 'is-error'">
        <template x-if="video?.src">
            <video :src="`${video.src}#t=0.1`" muted playsinline preload="metadata" class="video-tile-media" aria-hidden="true"></video>
        </template>
        <template x-if="video && !video.src && video.poster">
            <img :src="video.poster" alt="" class="video-tile-media">
        </template>
        <template x-if="video && !video.src && !video.poster">
            <span class="video-tile-media grid place-items-center text-2xl" aria-hidden="true">🎬</span>
        </template>
        <div class="min-w-0 flex-1 text-sm" aria-live="polite">
            <p x-show="label" class="font-semibold" x-text="label"></p>
            <template x-if="uploading">
                <div class="mt-1.5">
                    <div class="video-bar" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" :aria-label="t('ভিডিও আপলোড')">
                        <span :style="`width: ${percent}%`"></span>
                    </div>
                    <p class="mt-1 text-xs text-ink-2 tabular-nums" x-text="`${bn(percent)}% · ${sentLabel}`"></p>
                </div>
            </template>
            <p x-show="video?.error" class="font-semibold text-danger" role="alert" x-text="video?.error"></p>
        </div>
        <button type="button" class="photo-remove !static shrink-0" @click="remove()"
            :aria-label="uploading ? t('ভিডিও আপলোড বাতিল করুন') : t('ভিডিও সরান')">✕</button>
        <input type="hidden" name="video" :value="video?.id ?? ''" :disabled="!video || !video.id || (!!video.error && !video.attached)">
    </div>
    <label x-show="available" class="photo-add" :class="($data.anon || uploading) && 'is-off'">
        <input type="file" accept="video/*" class="sr-only" :disabled="$data.anon || uploading" @change="pick($event)">
        <span aria-hidden="true">🎬</span> <span x-text="video ? t('অন্য ভিডিও দিন') : t('ভিডিও যোগ করুন')">{{ __('ভিডিও যোগ করুন') }}</span>
        <span class="font-medium text-ink-2">{{ __('১ মিনিট পর্যন্ত') }}</span>
    </label>
</div>
