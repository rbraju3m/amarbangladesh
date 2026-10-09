{{--
    The site header on every public page (quiz and community). Needs an Alpine scope with `signedIn`,
    `myName`, `unread` and `meClick($event)`: the community component, or `siteNav` on quiz pages.
    Without JavaScript it still works as plain links ("Me" then offers sign-in).
--}}
@props(['active' => null])
@php($items = \App\Support\SiteNav::items())
<header {{ $attributes->merge(['class' => 'sticky top-0 z-30 border-b border-line bg-paper/90 backdrop-blur']) }}>
    <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
        <a href="{{ lroute('home') }}" class="flex min-w-0 items-center gap-2 font-bold tracking-wide">@include('partials.logo', ['class' => 'size-8 shrink-0'])<span class="truncate">{{ __('আমার বাংলাদেশ') }}</span></a>
        <nav class="ml-auto hidden items-center gap-1 md:flex" aria-label="{{ __('প্রধান') }}">
            @foreach ($items as $item)
                @if ($item['key'] === 'ask')
                    <a href="{{ $item['href'] }}" @click="navClick('ask')" class="ml-2 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-flag-red px-4 text-sm font-semibold text-white shadow-sm transition active:scale-95" @if ($active === 'ask') aria-current="page" @endif>{!! \App\Support\SiteNav::icon($item['icon'], 'size-4') !!}{{ __($item['label']) }}</a>
                @elseif ($item['key'] === 'me')
                    {{-- Signed out: "Log in". Signed in: the member's name, to their page. --}}
                    <a href="{{ $item['href'] }}" @click="meClick($event)" @class(['top-link flex items-center gap-2', 'is-on' => $active === 'me']) @if ($active === 'me') aria-current="page" @endif>
                        <span x-show="signedIn" x-cloak class="relative"><span class="avatar size-6 bg-flag-green text-xs" x-text="(myName || '?').slice(0, 1)"></span><span x-show="unread" class="notice-dot"></span></span>
                        <span x-text="signedIn ? (myName || @js(__('আমি'))) : @js(__('লগইন'))">{{ __('লগইন') }}</span><span x-show="unread" x-cloak class="sr-only">{{ __('নতুন নোটিফিকেশন আছে') }}</span>
                    </a>
                @else
                    <a href="{{ $item['href'] }}" @click="navClick(@js($item['key']))" @class(['top-link', 'is-on' => $active === $item['key']]) @if ($active === $item['key']) aria-current="page" @endif>{{ __($item['label']) }}</a>
                @endif
            @endforeach
        </nav>
        @include('partials.lang-switch', ['class' => 'ml-auto shrink-0 md:ml-2'])
    </div>
</header>
