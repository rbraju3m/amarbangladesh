{{--
    The home page's interactive Bangladesh: eight division shapes, shaded by how much people talk
    there. Hover (or keyboard focus) shows a label; tap / Enter picks a division: the map zooms into it
    and draws its districts (`GET /api/map/{division}` → `partials/district-map`, into the `districts`
    group), and the page shows its latest posts in `partials/division-posts`; a district can be picked
    the same way, "← সারা দেশ" or Escape zooms back out. Names sit on the shapes; counts only in the hover
    label (the `divisionMap` component in community.js). Without JavaScript each division is a link to
    its feed. $spots: App\Community\DivisionMap::spots().
--}}
<div class="relative mx-auto w-full max-w-[min(100%,26rem)]" @keydown.escape="zoomed && zoomOut()">
    <svg viewBox="0 0 400 552" x-ref="map" class="division-map w-full drop-shadow-[0_18px_30px_rgb(0_106_78/0.22)]" :class="{ 'has-spot': spot, 'is-zoomed': zoomed, 'has-district': district }" role="group" aria-label="{{ __('বাংলাদেশের মানচিত্র') }}">
        @foreach ($spots as $i => $s)
            <a href="{{ lroute('feed', ['area' => $s['slug']], false) }}" class="division-link" @click.prevent="pick('{{ $s['slug'] }}')"
                @pointerenter="$event.pointerType === 'mouse' && (hover = '{{ $s['slug'] }}')" @pointerleave="hover === '{{ $s['slug'] }}' && (hover = null)"
                @focus="$el.matches(':focus-visible') && (hover = '{{ $s['slug'] }}')" @blur="hover === '{{ $s['slug'] }}' && (hover = null)"
                aria-label="{{ __(':name বিভাগ', ['name' => $s['name']]) }}: {{ \App\Support\Lang::choice(':nটি আলোচনা', $s['count']) }}" :aria-label="ariaFor('{{ $s['slug'] }}')">
                <path d="{{ $s['path'] }}" data-division="{{ $s['slug'] }}" style="--i: {{ $i }}" class="division" data-level="{{ $s['level'] }}" :data-level="level('{{ $s['slug'] }}')" :class="{ 'is-on': spot === '{{ $s['slug'] }}', 'is-hover': hover === '{{ $s['slug'] }}' }" />
            </a>
        @endforeach
        {{-- The zoomed-in division's districts (partials/district-map), one listener for all of them --}}
        <g x-ref="districts" class="district-layer" role="group" :aria-label="zoomed ? name : null"
            @click="pickDistrictFrom($event)" @pointerover="$event.pointerType === 'mouse' && hoverDistrictFrom($event)" @pointerout="dHover = null"
            @focusin="$event.target.matches(':focus-visible') && hoverDistrictFrom($event)" @focusout="dHover = null"></g>
        {{-- Padma, Jamuna and Meghna, as on the quiz map --}}
        <g fill="none" stroke="#bfe3f2" stroke-width="3" stroke-linecap="round" opacity=".75" pointer-events="none" class="map-rivers">
            <path class="river" d="M55 215 C110 235 160 258 190 270 C215 290 225 315 225 335 C222 370 218 395 212 425" />
            <path class="river" d="M168 80 C172 150 178 220 190 270" />
            <path class="river" d="M320 165 C285 205 245 250 225 335" />
        </g>
    </svg>

    {{-- Each division's name on its shape; a pulsing dot where people posted this week --}}
    @foreach ($spots as $i => $s)
        <span class="map-name" x-show="!zoomed" x-transition.opacity style="left: {{ $s['x'] }}%; top: {{ $s['y'] }}%; --i: {{ $i }}" :class="(spot === '{{ $s['slug'] }}' || hover === '{{ $s['slug'] }}') && 'is-on'" aria-hidden="true">
            @if ($s['recent'])<span class="map-live" x-show="!topic"><span class="map-ping"></span></span>@endif{{ $s['name'] }}
        </span>
    @endforeach

    {{-- Hover / focus label: the division or district, its counts and its newest question --}}
    <template x-if="tip">
        <span class="map-tip" :style="tip.style" aria-hidden="true">
            <span class="block font-bold" x-text="tip.name"></span>
            <span class="block text-xs text-ink-2" x-text="tip.label"></span>
            <span x-show="tip.latest" class="map-tip-latest" x-text="tip.latest"></span>
        </span>
    </template>

    {{-- Zoomed in: the way back out --}}
    <button type="button" x-show="zoomed" x-cloak x-transition.opacity class="map-back" @click="zoomOut()">← {{ __('সারা দেশ') }}</button>
    <p x-show="zoomed && !districts.length && mapState === 'loading'" x-cloak class="map-loading" aria-hidden="true">{{ __('জেলাগুলো আসছে…') }}</p>
</div>

<div class="mt-4 flex flex-wrap justify-center gap-1.5" x-show="!zoomed">
    @foreach ($spots as $s)
        <a href="{{ lroute('feed', ['area' => $s['slug']], false) }}" class="filter-chip !text-xs" :class="spot === '{{ $s['slug'] }}' && 'is-on'" @click.prevent="pick('{{ $s['slug'] }}')">{{ $s['name'] }}</a>
    @endforeach
</div>
{{-- Zoomed in: the division's districts as chips (big tap targets, and a list for keyboards) --}}
<div class="mt-4 flex flex-wrap justify-center gap-1.5" x-show="zoomed && districts.length" x-cloak>
    <template x-for="d in districts" :key="d.slug">
        <a :href="d.url" class="filter-chip !text-xs" :class="district === d.slug && 'is-on'" @click.prevent="pickDistrict(d.slug)" x-text="d.short"></a>
    </template>
</div>
