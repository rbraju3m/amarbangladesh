{{-- Shell for community pages: the shared site header, footer and phone tab bar, plus the report/confirm sheet. --}}
@extends('layouts.site', [
    'siteName' => __('আমার বাংলাদেশ'),
    'script' => 'resources/js/community.js',
    'ogDescription' => $ogDescription ?? __('বাংলাদেশের মানুষের প্রশ্ন, উত্তর আর অভিজ্ঞতা। জিজ্ঞেস করুন, জানা থাকলে উত্তর দিন।'),
    // Link previews: the page's own photo if it has one, else the community card (`quiz:og-images --only=community`).
    'ogImage' => $ogImage ?? asset(\App\Support\Lang::isEnglish() ? 'images/og/en/community.png' : 'images/og/community.png'),
])

@section('content')
<main x-data="community" data-page="{{ $active ?? '' }}" class="flex min-h-dvh flex-col">
    <x-site.header :active="$active ?? null" />

    <div id="content" tabindex="-1" class="flex-1 outline-none">@yield('main')</div>

    <x-site.footer />
    <x-site.tabbar :active="$active ?? null" />

    @include('community.partials.photo-viewer')

    {{-- Share sheet: where the phone's own share sheet isn't there (desktop, Facebook/Messenger's in-app browser) --}}
    <div x-show="share" x-cloak x-modal="share" class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" aria-labelledby="share-title" @keydown.escape.window="share = null">
        <div class="absolute inset-0 bg-black/50" x-show="share" x-transition.opacity @click="share = null"></div>
        <div x-show="share" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0"
            tabindex="-1" data-autofocus class="relative w-full max-w-md rounded-t-[2rem] bg-paper px-5 outline-none pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] md:rounded-[2rem] md:pt-6 md:shadow-2xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-line md:hidden"></div>
            <h2 id="share-title" class="text-lg font-bold">{{ __('শেয়ার করুন') }}</h2>
            <p class="mt-1 line-clamp-2 text-sm text-ink-2" x-text="share?.title"></p>
            <div class="mt-5 flex flex-wrap justify-center gap-4">
                <button type="button" class="share-btn" @click="shareTo('whatsapp', share.title, share.url)"><span class="bg-[#25D366]">@include('partials.icon', ['name' => 'whatsapp'])</span><span>WhatsApp</span></button>
                <button type="button" class="share-btn" @click="shareTo('facebook', share.title, share.url)"><span class="bg-[#1877F2]">@include('partials.icon', ['name' => 'facebook'])</span><span>Facebook</span></button>
                <button type="button" class="share-btn" @click="shareTo('messenger', share.title, share.url)"><span class="bg-[#0084FF]">@include('partials.icon', ['name' => 'messenger'])</span><span>Messenger</span></button>
                <button type="button" class="share-btn" @click="shareTo('copy', share.title, share.url)"><span class="bg-ink !text-paper">@include('partials.icon', ['name' => 'link'])</span><span>{{ __('লিংক কপি') }}</span></button>
            </div>
            <label for="share-url" class="mt-5 block text-sm font-semibold">{{ __('লিংক') }}</label>
            <input id="share-url" readonly :value="share?.url" class="field mt-1 !text-sm" @focus="$el.select()" @click="$el.select()">
            <p x-show="inApp" class="mt-2 text-xs text-ink-2">{{ __('কপি না হলে লিংকটা চেপে ধরে কপি করুন।') }}</p>
            <button type="button" class="btn-ghost mt-4 w-full" @click="share = null">{{ __('বন্ধ করুন') }}</button>
        </div>
    </div>

    {{-- Report / confirm sheet --}}
    <div x-show="sheet" x-cloak x-modal="sheet" class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" :aria-labelledby="sheet && `sheet-${sheet.kind}-title`" @keydown.escape.window="sheet = null">
        <div class="absolute inset-0 bg-black/50" x-show="sheet" x-transition.opacity @click="sheet = null"></div>
        <div x-show="sheet" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0"
            tabindex="-1" data-autofocus class="relative w-full max-w-md rounded-t-[2rem] bg-paper px-5 outline-none pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] md:rounded-[2rem] md:pt-6 md:shadow-2xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-line md:hidden"></div>
            <template x-if="sheet?.kind === 'report'">
                <div>
                    <h2 id="sheet-report-title" class="text-lg font-bold">{{ __('কী সমস্যা দেখছেন?') }}</h2>
                    <p class="mt-1 text-sm text-ink-2">{{ __('আপনার রিপোর্ট গোপন থাকবে। কয়েকজন রিপোর্ট করলে লেখাটা লুকিয়ে যায়, তারপর আমরা দেখি।') }}</p>
                    <div class="mt-4 grid gap-2">
                        @foreach (\App\Models\Report::REASONS as $key => $label)
                            <button type="button" class="btn-ghost justify-start !text-base" @click="report('{{ $key }}')" :disabled="busy">{{ __($label) }}</button>
                        @endforeach
                    </div>
                </div>
            </template>
            <template x-if="sheet?.kind === 'delete'">
                <div>
                    <h2 id="sheet-delete-title" class="text-lg font-bold" x-text="sheet.type === 'post' ? t('পোস্টটা মুছে ফেলবেন?') : t('উত্তরটা মুছে ফেলবেন?')"></h2>
                    <p class="mt-1 text-sm text-ink-2">{{ __('মুছে ফেললে আর কেউ দেখতে পাবে না, ফেরানোও যাবে না।') }}</p>
                    <div class="mt-5 flex gap-2">
                        <button type="button" class="btn-ghost flex-1" @click="sheet = null">{{ __('থাক') }}</button>
                        <button type="button" class="btn-primary !min-h-12 flex-1 !text-base" @click="destroy()" :disabled="busy">{{ __('হ্যাঁ, মুছে ফেলুন') }}</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @include('community.partials.login-sheet')

    <div x-show="toast" x-cloak x-transition class="fixed inset-x-4 bottom-24 z-50 mx-auto max-w-sm rounded-2xl bg-ink px-4 py-3 text-center text-sm font-medium text-paper shadow-xl md:bottom-6" role="status" x-text="toast"></div>
</main>
@endsection
