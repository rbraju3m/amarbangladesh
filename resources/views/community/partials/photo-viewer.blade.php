{{--
    Full-screen photo viewer shared by every grid on the page (`viewer` in the community component):
    previous/next buttons, arrow keys, swipe, Escape; focus handling from x-modal.
--}}
<div x-show="viewer" x-cloak x-modal="viewer" class="photo-viewer" role="dialog" aria-modal="true" aria-label="{{ __('ছবি') }}"
    @keydown.escape.window="viewer && (viewer = null)" @keydown.arrow-left.window="viewer && stepPhoto(-1)" @keydown.arrow-right.window="viewer && stepPhoto(1)"
    @touchstart.passive="touchX = $event.touches[0].clientX" @touchend="touchX !== null && Math.abs($event.changedTouches[0].clientX - touchX) > 50 && stepPhoto($event.changedTouches[0].clientX < touchX ? 1 : -1); touchX = null">
    <div tabindex="-1" data-autofocus class="flex size-full flex-col outline-none" @click.self="viewer = null">
        <div class="flex items-center justify-between gap-2 p-3 text-white">
            <span class="text-sm font-semibold tabular-nums" aria-live="polite" x-text="viewer && viewer.photos.length > 1 ? t(':n/:total', { n: bn(viewer.index + 1), total: bn(viewer.photos.length) }) : ''"></span>
            <button type="button" class="photo-viewer-btn" @click="viewer = null" aria-label="{{ __('বন্ধ করুন') }}">✕</button>
        </div>
        <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-6" @click.self="viewer = null">
            <template x-if="viewer">
                <img :src="viewer.photos[viewer.index].src" :alt="viewer.photos[viewer.index].alt" :width="viewer.photos[viewer.index].width" :height="viewer.photos[viewer.index].height"
                    class="max-h-full max-w-full rounded-lg object-contain">
            </template>
            <button type="button" x-show="viewer && viewer.photos.length > 1" class="photo-viewer-btn absolute left-2" @click="stepPhoto(-1)" aria-label="{{ __('আগের ছবি') }}">‹</button>
            <button type="button" x-show="viewer && viewer.photos.length > 1" class="photo-viewer-btn absolute right-2" @click="stepPhoto(1)" aria-label="{{ __('পরের ছবি') }}">›</button>
        </div>
    </div>
</div>
