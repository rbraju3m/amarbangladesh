{{--
    Bangladesh outline (Natural Earth, projected) with one dot per location.
    $mode: 'hero' (decorative, gentle pulse) or 'reveal' (driven by scanSlug / lockedSlug).
--}}
{{-- With clickable places it is a group of buttons (role="img" would hide them from screen readers). --}}
<svg viewBox="0 0 400 552" class="{{ $class ?? '' }}" role="{{ $mode === 'hero' ? 'group' : 'img' }}" aria-label="{{ __('বাংলাদেশের মানচিত্র') }}">
    <defs>
        <linearGradient id="bd-fill-{{ $mode }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#7fbf8f" />
            <stop offset="1" stop-color="#2f8a5f" />
        </linearGradient>
    </defs>
    <path d="@include('partials.bd-map-path')" fill="url(#bd-fill-{{ $mode }})" stroke="#1f5f42" stroke-width="1.5" stroke-linejoin="round" opacity=".92" />
    {{-- Stylised Padma–Jamuna–Meghna rivers --}}
    <g fill="none" stroke="#bfe3f2" stroke-width="3" stroke-linecap="round" opacity=".85">
        <path class="river" d="M55 215 C110 235 160 258 190 270 C215 290 225 315 225 335 C222 370 218 395 212 425" />
        <path class="river" d="M168 80 C172 150 178 220 190 270" />
        <path class="river" d="M320 165 C285 205 245 250 225 335" />
    </g>
    {{-- Dots are rendered server-side: Alpine's x-for <template> does not work inside SVG. --}}
    @foreach ($locations as $loc)
        @php($cx = $loc['x'] * 4)
        @php($cy = $loc['y'] * 5.52)
        @if ($mode === 'reveal')
            <circle x-show="lockedSlug === '{{ $loc['slug'] }}'" class="map-pulse" cx="{{ $cx }}" cy="{{ $cy }}" r="10" fill="var(--red)" />
            <circle class="map-dot" cx="{{ $cx }}" cy="{{ $cy }}" r="7" fill="#ffffff" stroke="#14211b" stroke-width="2"
                :r="lockedSlug === '{{ $loc['slug'] }}' ? 14 : (scanSlug === '{{ $loc['slug'] }}' ? 11 : 7)"
                :fill="lockedSlug === '{{ $loc['slug'] }}' || scanSlug === '{{ $loc['slug'] }}' ? 'var(--red)' : '#ffffff'"
                :opacity="lockedSlug && lockedSlug !== '{{ $loc['slug'] }}' ? .3 : 1" />
        @else
            {{-- Hover (mouse) or tap focuses a place; the landing page shows a tooltip and highlights its card. --}}
            {{-- Keyboard: Tab focuses a place (tooltip shows), Enter or Space opens it. --}}
            <g data-place class="cursor-pointer outline-none" role="button" tabindex="0" aria-label="{{ $loc['name'] }}: {{ $loc['title'] }}"
                @pointerenter="$event.pointerType === 'mouse' && (focusSlug = '{{ $loc['slug'] }}')"
                @pointerleave="$event.pointerType === 'mouse' && (focusSlug = null)"
                @focus="focusSlug = '{{ $loc['slug'] }}'" @blur="focusSlug === '{{ $loc['slug'] }}' && (focusSlug = null)"
                @keydown.enter.prevent="openPlace('{{ $loc['slug'] }}')" @keydown.space.prevent="openPlace('{{ $loc['slug'] }}')"
                @click="tapDot('{{ $loc['slug'] }}')">
                <circle x-show="focusSlug === '{{ $loc['slug'] }}'" class="map-pulse" cx="{{ $cx }}" cy="{{ $cy }}" r="10" fill="var(--red)" />
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="20" fill="transparent" />
                <circle class="map-dot" cx="{{ $cx }}" cy="{{ $cy }}" r="7" fill="#ffffff" stroke="#14211b" stroke-width="2"
                    :r="focusSlug === '{{ $loc['slug'] }}' ? 12 : 7"
                    :fill="focusSlug === '{{ $loc['slug'] }}' ? 'var(--red)' : '#ffffff'" />
            </g>
        @endif
    @endforeach
</svg>
