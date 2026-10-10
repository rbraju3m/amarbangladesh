{{--
    Photos for a post or a top-level answer (resources/js/photos.js): up to 4, shrunk in the browser
    and uploaded as soon as they're picked; the form sends their ids. Edit forms (`fromItem`) start
    with the item's photos from its <template data-raw-photos>. Not offered with "anonymous" on.
--}}
<div data-photos @class([$class ?? ''])
    x-data="photoPicker({{ ($fromItem ?? false) ? "JSON.parse(\$el.closest('article')?.querySelector('template[data-raw-photos]')?.content.textContent || '[]')" : '[]' }})">
    <div x-show="photos.length" x-cloak class="photo-tiles">
        <template x-for="(p, i) in photos" :key="p.key">
            <div class="photo-tile" :class="p.error && 'is-error'">
                <img :src="p.src" alt="" class="size-full object-cover">
                <input type="hidden" name="photos[]" :value="p.id" :disabled="!p.id">
                <span x-show="!p.id && !p.error" class="photo-progress" :style="`--p: ${Math.round(p.progress * 100)}`" role="progressbar"
                    :aria-valuenow="Math.round(p.progress * 100)" aria-valuemin="0" aria-valuemax="100" :aria-label="t('ছবি :n আপলোড হচ্ছে', { n: bn(i + 1) })"></span>
                <p x-show="p.error" class="photo-error" role="alert" x-text="p.error"></p>
                <button type="button" class="photo-remove" @click="remove(p.key)" :aria-label="t('ছবি :n সরান', { n: bn(i + 1) })">✕</button>
            </div>
        </template>
    </div>
    <label class="photo-add" :class="($data.anon || photos.length >= max) && 'is-off'">
        <input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/*" multiple class="sr-only"
            :disabled="$data.anon || photos.length >= max" @change="pick($event)">
        <span aria-hidden="true">📷</span> {{ __('ছবি যোগ করুন') }}
        <span class="font-medium text-ink-2 tabular-nums" x-text="`${bn(photos.length)}/${bn(max)}`">০/৪</span>
    </label>
    <p x-show="$data.anon" x-cloak class="mt-1 text-xs text-ink-2">{{ __('বেনামী পোস্টে ছবি দেওয়া যায় না।') }}</p>
</div>
