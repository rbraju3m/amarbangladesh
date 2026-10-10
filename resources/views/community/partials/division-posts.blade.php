{{--
    The picked division's (or, zoomed in, district's) latest posts (shares the `divisionMap` scope with partials/division-map).
    Shown once a division is picked. Desktop: under the ask box. Phones: under the map.
--}}
<div x-show="spot" x-cloak class="rounded-3xl border border-line bg-card p-4 text-left lg:p-5">
    {{-- Screen readers hear a short summary, not every card --}}
    <p class="sr-only" aria-live="polite" x-text="announcement"></p>
    <div class="flex items-baseline justify-between gap-2">
        <p class="text-lg font-bold" x-text="name"></p>
        <button type="button" class="act-quiet !min-h-8 !px-2 text-sm" @click="spot = null" aria-label="{{ __('বন্ধ করুন') }}">✕</button>
    </div>
    <div x-show="state === 'slow'" class="mt-3 space-y-2" aria-hidden="true"><div class="h-16 animate-pulse rounded-2xl bg-paper-2"></div><div class="h-16 animate-pulse rounded-2xl bg-paper-2"></div></div>
    <div x-show="html" class="mt-3 space-y-2 transition-opacity" :class="state === 'loading' && 'opacity-60'" x-html="html"></div>
    <p x-show="state === 'empty'" class="mt-2 text-sm text-ink-2" x-text="emptyText">{{ __('এই বিভাগে এখনো কোনো আলোচনা নেই। প্রথম প্রশ্নটা আপনিই করুন!') }}</p>
    <p x-show="state === 'error'" class="mt-2 text-sm text-danger">{{ __('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।') }}</p>
    <div class="mt-3 grid grid-cols-2 gap-2">
        <a :href="feedUrl" class="btn-ghost !min-h-11 !px-2 text-sm">{{ __('সব আলোচনা দেখুন') }}</a>
        <a href="{{ lroute('ask', [], false) }}" :href="askUrl" class="btn-ghost !min-h-11 !px-2 text-sm">{{ __('জিজ্ঞেস করুন') }}</a>
    </div>
</div>
