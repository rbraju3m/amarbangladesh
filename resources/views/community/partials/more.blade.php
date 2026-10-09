{{--
    The next page of a list: a plain link without JavaScript, an endless list with it (resources/js/infinite.js).
    $list: CSS selector of the list (its items carry data-item); $href: the next page's URL, or null on the last page.
--}}
@if ($href)
    <div x-data="infinite(@js($list))" class="mt-5">
        <a data-next href="{{ $href }}" class="btn-ghost w-full" @click.prevent="more()" :aria-busy="(state === 'loading').toString()">
            <span x-show="state === 'idle'">{{ __('আরও দেখুন') }}</span>
            <span x-show="state === 'loading'" x-cloak>{{ __('আনছি…') }}</span>
            <span x-show="state === 'error'" x-cloak>{{ __('আনা গেলো না · আবার চেষ্টা করুন') }}</span>
        </a>
        <p class="sr-only" aria-live="polite" x-text="announcement"></p>
    </div>
@endif
