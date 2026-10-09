{{--
    The home page's interactive Bangladesh: eight division shapes, shaded by how much people talk
    there. Hover (or keyboard focus) shows a label; tap / Enter picks a division, whose latest posts the
    page shows in `partials/division-posts`. Names sit on the shapes; counts only in the hover label (the `divisionMap` component in community.js). Without
    JavaScript each division is a link to its feed. $spots: App\Community\DivisionMap::spots().
--}}
<div class="relative mx-auto w-full max-w-[min(100%,26rem)]">
    <svg viewBox="0 0 400 552" class="division-map w-full drop-shadow-[0_18px_30px_rgb(0_106_78/0.22)]" role="group" aria-label="{{ __('বাংলাদেশের মানচিত্র') }}">
        @foreach ($spots as $s)
            <a href="{{ lroute('feed', ['area' => $s['slug']], false) }}" class="division-link" @click.prevent="pick('{{ $s['slug'] }}')"
                @pointerenter="$event.pointerType === 'mouse' && (hover = '{{ $s['slug'] }}')" @pointerleave="hover === '{{ $s['slug'] }}' && (hover = null)"
                @focus="$el.matches(':focus-visible') && (hover = '{{ $s['slug'] }}')" @blur="hover === '{{ $s['slug'] }}' && (hover = null)"
                aria-label="{{ __(':name বিভাগ', ['name' => $s['name']]) }}: {{ \App\Support\Lang::choice(':nটি আলোচনা', $s['count']) }}">
                <path d="{{ $s['path'] }}" class="division level-{{ $s['level'] }}" :class="{ 'is-on': spot === '{{ $s['slug'] }}', 'is-hover': hover === '{{ $s['slug'] }}' }" />
            </a>
        @endforeach
        {{-- Padma, Jamuna and Meghna, as on the quiz map --}}
        <g fill="none" stroke="#bfe3f2" stroke-width="3" stroke-linecap="round" opacity=".75" pointer-events="none">
            <path class="river" d="M55 215 C110 235 160 258 190 270 C215 290 225 315 225 335 C222 370 218 395 212 425" />
            <path class="river" d="M168 80 C172 150 178 220 190 270" />
            <path class="river" d="M320 165 C285 205 245 250 225 335" />
        </g>
    </svg>

    {{-- Each division's name on its shape; a pulsing dot where people posted this week --}}
    @foreach ($spots as $s)
        <span class="map-name" style="left: {{ $s['x'] }}%; top: {{ $s['y'] }}%" :class="(spot === '{{ $s['slug'] }}' || hover === '{{ $s['slug'] }}') && 'is-on'" aria-hidden="true">
            @if ($s['recent'])<span class="map-live"><span class="map-ping"></span></span>@endif{{ $s['name'] }}
        </span>
    @endforeach

    {{-- Hover / focus label --}}
    @foreach ($spots as $s)
        <span x-show="hover === '{{ $s['slug'] }}' && spot !== '{{ $s['slug'] }}'" x-cloak class="map-tip" style="left: {{ $s['x'] }}%; top: {{ $s['y'] }}%" aria-hidden="true">
            <span class="block font-bold">{{ __(':name বিভাগ', ['name' => $s['name']]) }}</span>
            <span class="block text-xs text-ink-2">{{ \App\Support\Lang::choice(':nটি আলোচনা', $s['count']) }}@if ($s['recent']) · {{ __('এই সপ্তাহে :n', ['n' => \App\Support\Lang::num($s['recent'])]) }}@endif</span>
        </span>
    @endforeach
</div>

<div class="mt-4 flex flex-wrap justify-center gap-1.5">
    @foreach ($spots as $s)
        <a href="{{ lroute('feed', ['area' => $s['slug']], false) }}" class="filter-chip !text-xs" :class="spot === '{{ $s['slug'] }}' && 'is-on'" @click.prevent="pick('{{ $s['slug'] }}')">{{ $s['name'] }}</a>
    @endforeach
</div>
