{{-- Shell for community pages: the shared site header, footer and phone tab bar, plus the report/confirm sheet. --}}
@extends('layouts.site', [
    'siteName' => __('আমার বাংলাদেশ'),
    'script' => 'resources/js/community.js',
    'ogDescription' => $ogDescription ?? __('বাংলাদেশের মানুষের প্রশ্ন, উত্তর আর অভিজ্ঞতা। জিজ্ঞেস করুন, জানা থাকলে উত্তর দিন।'),
])

@section('content')
<main x-data="community" data-page="{{ $active ?? '' }}" class="flex min-h-dvh flex-col">
    <x-site.header :active="$active ?? null" />

    <div class="flex-1">@yield('main')</div>

    <x-site.footer />
    <x-site.tabbar :active="$active ?? null" />

    {{-- Report / confirm sheet --}}
    <div x-show="sheet" x-cloak x-modal="sheet" class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" @keydown.escape.window="sheet = null">
        <div class="absolute inset-0 bg-black/50" x-show="sheet" x-transition.opacity @click="sheet = null"></div>
        <div x-show="sheet" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0"
            tabindex="-1" data-autofocus class="relative w-full max-w-md rounded-t-[2rem] bg-paper px-5 outline-none pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] md:rounded-[2rem] md:pt-6 md:shadow-2xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-line md:hidden"></div>
            <template x-if="sheet?.kind === 'report'">
                <div>
                    <h2 class="text-lg font-bold">{{ __('কী সমস্যা দেখছেন?') }}</h2>
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
                    <h2 class="text-lg font-bold" x-text="sheet.type === 'post' ? t('পোস্টটা মুছে ফেলবেন?') : t('উত্তরটা মুছে ফেলবেন?')"></h2>
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
