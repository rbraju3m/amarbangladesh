{{-- Phone tab bar: thumb-reachable, "ask" as the raised action. Same Alpine scope as the header. The spacer keeps the page's end clear of it. --}}
@props(['active' => null])
<div class="h-[calc(4.5rem+env(safe-area-inset-bottom))] md:hidden" aria-hidden="true"></div>
<nav {{ $attributes->merge(['class' => 'fixed inset-x-0 bottom-0 z-30 border-t border-line bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden']) }} aria-label="{{ __('প্রধান') }}">
    <div class="mx-auto grid max-w-md grid-cols-5">
        @foreach (\App\Support\SiteNav::items() as $item)
            @php($on = $active === $item['key'])
            @if ($item['key'] === 'ask')
                <a href="{{ $item['href'] }}" @click="navClick('ask')" class="tab-link" @if ($on) aria-current="page" @endif>
                    <span class="-mt-5 flex size-12 items-center justify-center rounded-2xl bg-flag-red text-white shadow-[0_8px_20px_-6px_rgb(224_58_62/0.6)]">{!! \App\Support\SiteNav::icon($item['icon']) !!}</span>
                    <span @class(['text-ink' => $on])>{{ __($item['label']) }}</span>
                </a>
            @elseif ($item['key'] === 'me')
                <a href="{{ $item['href'] }}" @click="meClick($event)" @class(['tab-link', 'is-on' => $on]) @if ($on) aria-current="page" @endif>
                    <span x-show="signedIn" x-cloak class="relative"><span class="avatar size-6 bg-flag-green text-xs" x-text="(myName || '?').slice(0, 1)"></span><span x-show="unread" class="notice-dot"></span></span>
                    <span x-show="!signedIn">{!! \App\Support\SiteNav::icon($item['icon']) !!}</span>
                    <span class="max-w-16 truncate" x-text="signedIn ? (myName || @js(__('আমি'))) : @js(__('লগইন'))">{{ __('লগইন') }}</span><span x-show="unread" x-cloak class="sr-only">{{ __('নতুন নোটিফিকেশন আছে') }}</span>
                </a>
            @else
                <a href="{{ $item['href'] }}" @click="navClick(@js($item['key']))" @class(['tab-link', 'is-on' => $on]) @if ($on) aria-current="page" @endif>{!! \App\Support\SiteNav::icon($item['icon']) !!}<span>{{ __($item['label']) }}</span></a>
            @endif
        @endforeach
    </div>
</nav>
