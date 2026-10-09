{{-- Shell for community pages: header (desktop nav), content, a bottom tab bar on phones, and the shared report/confirm sheet. --}}
@extends('layouts.site', [
    'siteName' => __('আমার বাংলাদেশ'),
    'script' => 'resources/js/community.js',
    'ogDescription' => $ogDescription ?? __('বাংলাদেশের মানুষের প্রশ্ন, উত্তর আর অভিজ্ঞতা। জিজ্ঞেস করুন, জানা থাকলে উত্তর দিন।'),
])

@php
    $tabs = [
        ['home', lroute('home'), 'হোম', 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z'],
        ['feed', lroute('feed', [], false), 'আলোচনা', 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12Z'],
        ['ask', lroute('ask', [], false), 'জিজ্ঞেস করুন', 'M12 5v14M5 12h14'],
        ['me', lroute('me', [], false), 'আমি', 'M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
    ];
    $active = $active ?? null;
    $svg = fn ($d, $class = 'size-6') => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$d.'"/></svg>';
@endphp

@section('content')
<main x-data="community" data-page="{{ $active }}" class="min-h-dvh pb-28 md:pb-12">
    <header class="sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
            <a href="{{ lroute('home') }}" class="flex items-center gap-2 font-bold tracking-wide">@include('partials.logo', ['class' => 'size-8']){{ __('আমার বাংলাদেশ') }}</a>
            @include('partials.lang-switch', ['class' => 'ml-auto md:order-last md:ml-3'])
            <nav class="hidden items-center gap-1 md:ml-auto md:flex" aria-label="{{ __('প্রধান') }}">
                @foreach ($tabs as [$key, $href, $label, $path])
                    @if ($key === 'ask')
                        <a href="{{ $href }}" class="ml-2 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-flag-red px-4 text-sm font-semibold text-white shadow-sm transition active:scale-95">{!! $svg($path, 'size-4') !!}{{ __($label) }}</a>
                    @elseif ($key === 'me')
                        {{-- Signed out: "লগইন" opens the sheet. Signed in: the member's name, to their page. --}}
                        <a href="{{ $href }}" @click="if (!signedIn) { $event.preventDefault(); openLogin().then(() => openMe(), () => {}) }"
                            @class(['top-link flex items-center gap-2', 'is-on' => $active === $key])>
                            <span x-show="signedIn" x-cloak class="relative"><span class="avatar size-6 bg-flag-green text-xs" x-text="(myName || '?').slice(0, 1)"></span><span x-show="unread" class="notice-dot"></span></span>
                            <span x-text="signedIn ? (myName || @js(__($label))) : @js(__('লগইন'))">{{ __($label) }}</span><span x-show="unread" x-cloak class="sr-only">{{ __('নতুন নোটিফিকেশন আছে') }}</span>
                        </a>
                    @else
                        <a href="{{ $href }}" @class(['top-link', 'is-on' => $active === $key]) @if ($active === $key) aria-current="page" @endif>{{ __($label) }}</a>
                    @endif
                @endforeach
            </nav>
        </div>
    </header>

    @yield('main')

    {{-- Phone tab bar: thumb-reachable, with "ask" as the raised centre action. --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="{{ __('প্রধান') }}">
        <div class="mx-auto grid max-w-md grid-cols-4">
            @foreach ($tabs as [$key, $href, $label, $path])
                @if ($key === 'ask')
                    <a href="{{ $href }}" class="tab-link" @if ($active === $key) aria-current="page" @endif>
                        <span class="-mt-5 flex size-12 items-center justify-center rounded-2xl bg-flag-red text-white shadow-[0_8px_20px_-6px_rgb(224_58_62/0.6)]">{!! $svg($path) !!}</span>
                        <span @class(['text-ink' => $active === $key])>{{ __($label) }}</span>
                    </a>
                @elseif ($key === 'me')
                    <a href="{{ $href }}" @click="if (!signedIn) { $event.preventDefault(); openLogin().then(() => openMe(), () => {}) }"
                        @class(['tab-link', 'is-on' => $active === $key]) @if ($active === $key) aria-current="page" @endif>
                        <span x-show="signedIn" x-cloak class="relative"><span class="avatar size-6 bg-flag-green text-xs" x-text="(myName || '?').slice(0, 1)"></span><span x-show="unread" class="notice-dot"></span></span>
                        <span x-show="!signedIn">{!! $svg($path) !!}</span>
                        <span class="max-w-16 truncate" x-text="signedIn ? (myName || @js(__($label))) : @js(__('লগইন'))">{{ __($label) }}</span><span x-show="unread" x-cloak class="sr-only">{{ __('নতুন নোটিফিকেশন আছে') }}</span>
                    </a>
                @else
                    <a href="{{ $href }}" @class(['tab-link', 'is-on' => $active === $key]) @if ($active === $key) aria-current="page" @endif>{!! $svg($path) !!}<span>{{ __($label) }}</span></a>
                @endif
            @endforeach
        </div>
    </nav>

    {{-- Report / confirm sheet --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" @keydown.escape.window="sheet = null">
        <div class="absolute inset-0 bg-black/50" x-show="sheet" x-transition.opacity @click="sheet = null"></div>
        <div x-show="sheet" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0"
            class="relative w-full max-w-md rounded-t-[2rem] bg-paper px-5 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] md:rounded-[2rem] md:pt-6 md:shadow-2xl">
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
