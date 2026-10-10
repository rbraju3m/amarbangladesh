{{--
    The site header on every public page (quiz and community). Needs an Alpine scope with `signedIn`,
    `myName`, `unread` and `meClick($event)`: the community component, or `siteNav` on quiz pages.
    Without JavaScript it still works as plain links ("Me" then offers sign-in).
--}}
@props(['active' => null])
{{-- Desktop order: sections, then the "ask" button, then the member. --}}
@php($items = collect(\App\Support\SiteNav::items())->sortBy(fn ($i) => ['ask' => 1, 'me' => 2][$i['key']] ?? 0)->values())
<header {{ $attributes->merge(['class' => 'sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur']) }}>
    {{-- Keyboard users skip the menu; the page puts id="content" where its own content starts. --}}
    <a href="#content" class="skip-link">{{ __('মূল অংশে যান') }}</a>
    <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
        <a href="{{ lroute('home') }}" class="flex min-w-0 items-center gap-2 font-bold tracking-wide">@include('partials.logo', ['class' => 'size-8 shrink-0'])<span class="truncate">{{ __('আমার বাংলাদেশ') }}</span></a>
        <nav class="ml-auto hidden items-center gap-1 md:flex" aria-label="{{ __('প্রধান') }}">
            @foreach ($items as $item)
                @if ($item['key'] === 'ask')
                    <a href="{{ $item['href'] }}" @click="navClick('ask')" class="ml-2 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-flag-red px-4 text-sm font-semibold text-white shadow-sm transition active:scale-95" @if ($active === 'ask') aria-current="page" @endif>{!! \App\Support\SiteNav::icon($item['icon'], 'size-4') !!}{{ __($item['label']) }}</a>
                @elseif ($item['key'] === 'me')
                    {{-- Signed out: "Log in". Signed in: the member's name, to their page. --}}
                    <a href="{{ $item['href'] }}" @click="meClick($event)" @class(['top-link flex items-center gap-2', 'is-on' => $active === 'me']) @if ($active === 'me') aria-current="page" @endif>
                        <span x-show="signedIn" x-cloak data-me="in" class="relative"><span class="avatar size-6 bg-flag-green text-xs" data-me="letter" x-text="(myName || '?').slice(0, 1)"></span><span x-show="unread" class="notice-dot"></span></span>
                        <span data-me="name" x-text="signedIn ? (myName || @js(__('আমি'))) : @js(__('লগইন'))">{{ __('লগইন') }}</span><span x-show="unread" x-cloak class="sr-only">{{ __('নতুন নোটিফিকেশন আছে') }}</span>
                    </a>
                @else
                    <a href="{{ $item['href'] }}" @click="navClick(@js($item['key']))" @class(['top-link', 'is-on' => $active === $item['key']]) @if ($active === $item['key']) aria-current="page" @endif>{{ __($item['label']) }}</a>
                @endif
            @endforeach
        </nav>
        <a href="{{ lroute('search', [], false) }}" @class(['ml-auto flex size-10 shrink-0 items-center justify-center rounded-full text-ink-2 transition hover:bg-paper-2 hover:text-ink md:ml-2', 'bg-paper-2 text-ink' => $active === 'search']) aria-label="{{ __('খুঁজুন') }}" @if ($active === 'search') aria-current="page" @endif>{!! \App\Support\SiteNav::icon('M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z', 'size-5') !!}</a>
        @include('partials.lang-switch', ['class' => 'shrink-0'])
        @include('partials.me-prefill')
    </div>
</header>
