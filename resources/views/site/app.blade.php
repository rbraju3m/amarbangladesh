@extends('layouts.site', isset($result) ? [
    'ogTitle' => ($result->display_name ? \App\Support\Bangla::possessive($result->display_name) : 'আমার').' বাংলাদেশ হলো '.$result->location->name_bn.' '.$result->location->emoji,
    'ogDescription' => $result->location->title_bn.' · ভাইব ম্যাচ '.\App\Support\Bangla::digits($result->match_pct).'%। তোমার বাংলাদেশ কোথায়? মাত্র ১ মিনিটে খুঁজে দেখো →',
    'ogImage' => $result->location->ogImageUrl(),
    'pageTitle' => $result->location->emoji.' '.$result->location->name_bn.' — তোমার বাংলাদেশ কোথায়?',
    'noindex' => true,
] : [])

@section('content')
@php($isShared = isset($boot['shared']))
<script type="application/json" id="boot">{!! json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

<main x-data="quiz(JSON.parse(document.getElementById('boot').textContent))" :style="`--accent: ${accent}`" class="relative overflow-x-clip">

    {{-- ===================== LANDING ===================== --}}
    <section x-show="screen === 'landing'" @if ($isShared) x-cloak @endif class="screen pb-10 md:max-w-2xl lg:max-w-6xl lg:px-8"
        @click="$event.target.closest('[data-place]') || (focusSlug = null)">
        <header class="flex items-center justify-between py-3 lg:py-4">
            <span class="flex items-center gap-2 text-sm font-semibold tracking-wide">@include('partials.logo', ['class' => 'size-8'])আমার বাংলাদেশ</span>
            <a href="{{ route('feed', [], false) }}" class="chip !py-1 transition hover:border-ink-2" @click="trackCommunity('landing_header', 'feed')">💬 আলোচনা</a>
        </header>

        <div class="lg:grid lg:flex-1 lg:grid-cols-2 lg:items-center lg:gap-16 lg:py-6">
            {{-- On phones the map's height is capped (400×552, so 25dvh wide ≈ 34dvh tall) to keep the play button above the fold, Messenger's browser included. --}}
            <div class="relative mx-auto -mb-2 w-[min(68%,25dvh)] max-w-64 lg:mb-0 lg:w-[78%] lg:max-w-sm">
                @include('partials.bd-map', ['mode' => 'hero', 'class' => 'w-full drop-shadow-[0_18px_30px_rgb(0_106_78/0.25)]', 'locations' => $boot['locations']])
                <div class="transition-opacity duration-200" :class="focusSlug && 'opacity-0'" aria-hidden="true">
                    <span class="animate-pop absolute top-[18%] -left-6 rounded-full bg-card px-3 py-1 text-sm font-semibold shadow-lg [animation-delay:.2s]">🌿 সিলেট?</span>
                    <span class="animate-pop absolute top-[52%] -right-8 rounded-full bg-card px-3 py-1 text-sm font-semibold shadow-lg [animation-delay:.45s]">🌊 কক্সবাজার?</span>
                    <span class="animate-pop absolute bottom-[14%] -left-4 rounded-full bg-card px-3 py-1 text-sm font-semibold shadow-lg [animation-delay:.7s]">🐅 সুন্দরবন?</span>
                </div>
                {{-- Tooltip for the focused map dot / place card --}}
                <template x-if="focusPlace">
                    <div class="animate-pop pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-[calc(100%+16px)] rounded-2xl bg-card px-3 py-2 text-center whitespace-nowrap shadow-xl"
                        :style="`left: ${focusPlace.x}%; top: ${focusPlace.y}%`">
                        <div class="font-bold" x-text="`${focusPlace.emoji} ${focusPlace.name_bn}`"></div>
                        <div class="text-xs text-ink-2" x-text="focusPlace.title_bn"></div>
                    </div>
                </template>
            </div>

            <div class="relative z-10 text-center lg:text-left">
                <h1 class="text-[2.3rem] leading-[1.15] font-bold tracking-tight sm:text-[2.6rem] lg:text-[4.2rem]">
                    তোমার <span class="text-green-text">বাংলাদেশ</span><br>কোথায়?
                </h1>
                <p class="mx-auto mt-2 max-w-xs text-base leading-relaxed text-ink-2 sm:text-lg lg:mx-0 lg:mt-5 lg:max-w-md lg:text-xl">
                    বাংলাদেশের কোন জায়গাটা তোমার personality-র সাথে সবচেয়ে বেশি মেলে?
                </p>

                <button type="button" class="btn-primary group mt-5 lg:mt-7 lg:w-auto lg:px-10" @click="start()">
                    আমার বাংলাদেশ খুঁজে দেখি <span aria-hidden="true" class="transition-transform duration-200 group-hover:translate-x-1">→</span>
                </button>

                @if ($totalPlays ?? null)
                    <p class="mt-3 text-sm text-ink-2">👥 {{ \App\Support\Bangla::digits($totalPlays) }} জন এর মধ্যেই খুঁজে পেয়েছে তাদের বাংলাদেশ</p>
                @endif
                <ul class="mt-4 flex flex-wrap justify-center gap-2 lg:mt-5 lg:justify-start" aria-label="কুইজের তথ্য">
                    <li class="chip">⏱️ মাত্র ১ মিনিট</li>
                    <li class="chip">✨ {{ \App\Support\Bangla::digits(count($boot['questions'])) }}টি প্রশ্ন</li>
                    <li class="chip">🇧🇩 {{ \App\Support\Bangla::digits(count($boot['locations'])) }}টি বাংলাদেশ</li>
                </ul>
                <p class="mt-6 hidden text-sm text-ink-2 lg:block">👈 ম্যাপের বিন্দুগুলোতে মাউস রাখো, ক্লিক করলে বিস্তারিত</p>
            </div>
        </div>

        <div class="mt-12">
            <h2 class="text-center text-sm font-semibold text-ink-2">এদের মধ্যে একটা তুমি 👇</h2>
            <p class="mb-3 text-center text-xs text-ink-2">যেকোনোটায় ট্যাপ করে দেখো</p>
            <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pt-2 pb-3 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0 lg:grid-cols-9">
                @foreach ($boot['locations'] as $loc)
                    <button type="button" data-place class="place-card w-36 shrink-0 snap-start overflow-hidden rounded-2xl border border-line bg-card text-left md:w-auto"
                        :class="focusSlug === '{{ $loc['slug'] }}' && 'is-focus'"
                        @pointerenter="$event.pointerType === 'mouse' && (focusSlug = '{{ $loc['slug'] }}')"
                        @pointerleave="$event.pointerType === 'mouse' && (focusSlug = null)"
                        @click="focusSlug = '{{ $loc['slug'] }}'; openPlace('{{ $loc['slug'] }}')">
                        <span class="relative block">
                            <img src="{{ $loc['illustration'] }}" alt="" loading="lazy" width="144" height="96" class="h-24 w-full object-cover md:h-auto md:aspect-[4/3]">
                            <span class="absolute bottom-1.5 left-1.5 flex size-8 items-center justify-center rounded-full bg-card/90 text-base shadow" aria-hidden="true">{{ $loc['emoji'] }}</span>
                        </span>
                        <span class="block px-3 py-2 lg:px-2.5">
                            <span class="block font-semibold whitespace-nowrap">{{ $loc['name_bn'] }}</span>
                            <span class="block text-xs text-ink-2" :class="focusSlug === '{{ $loc['slug'] }}' ? 'whitespace-normal' : 'truncate'">{{ $loc['title_bn'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- The community, below the quiz: the quiz stays the first thing anyone sees. --}}
        <section class="mt-14 rounded-[2rem] border border-line bg-card p-5 md:p-7 lg:grid lg:grid-cols-[5fr_6fr] lg:gap-10 lg:p-9" aria-labelledby="community-title">
            <div>
                <p class="text-sm font-semibold text-green-text">🇧🇩 আমার বাংলাদেশ · আলোচনা</p>
                <h2 id="community-title" class="mt-1 text-2xl leading-snug font-bold lg:text-3xl">বাংলাদেশিদের প্রশ্ন,<br>বাংলাদেশিরাই উত্তর দেয়</h2>
                <p class="mt-2 text-ink-2">ডাক্তার, পড়াশোনা, চাকরি, সরকারি কাজ, ঘোরাঘুরি — যা জানতে চাও জিজ্ঞেস করো, জানা থাকলে উত্তর দাও।</p>
                <a href="{{ route('ask', [], false) }}" class="ask-prompt mt-5" @click="trackCommunity('landing', 'ask')">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-flag-red/10 text-xl" aria-hidden="true">✍️</span>
                    <span class="flex-1 text-ink-2">কী জানতে চাও?</span>
                    <span class="pill bg-flag-red px-3 py-1.5 text-sm text-white">জিজ্ঞেস করো</span>
                </a>
            </div>
            <div class="mt-6 lg:mt-0" x-data="communityPreview(3)">
                <p class="mb-3 text-sm font-semibold text-ink-2">এখন যা নিয়ে কথা হচ্ছে</p>
                <div x-show="count === null" class="space-y-3" aria-hidden="true">
                    <div class="h-24 animate-pulse rounded-3xl bg-paper-2"></div><div class="h-24 animate-pulse rounded-3xl bg-paper-2"></div>
                </div>
                <div x-show="count" x-cloak class="space-y-3" x-html="html"></div>
                <p x-show="count === 0" x-cloak class="rounded-3xl border border-dashed border-line px-4 py-6 text-center text-ink-2">🌱 আলোচনা সবে শুরু হচ্ছে, প্রথম প্রশ্নটা তোমার হোক!</p>
                <a href="{{ route('feed', [], false) }}" class="btn-ghost mt-3 w-full" @click="trackCommunity('landing', 'feed')">সব আলোচনা দেখো →</a>
            </div>
        </section>

        <footer class="mt-auto pt-10 text-center text-xs leading-relaxed text-ink-2">
            কোনো লগইন নেই। তোমার নাম, ফোন বা ইমেইল আমরা চাই না।
        </footer>
    </section>

    {{-- ===================== TEASER (someone shared their result) =====================
         Compact so the play button sits above the fold on a phone, even inside Messenger's browser. --}}
    @if ($isShared)
        @php($s = $boot['shared'])
        @php($friend = $s['name'] ? \App\Support\Bangla::possessive($s['name']) : 'তোমার বন্ধুর')
        <section x-show="screen === 'teaser'" class="screen justify-center py-6 text-center md:max-w-md" style="--accent: {{ $s['location']['accent'] }}">
            <p class="text-sm font-semibold text-ink-2">{{ $friend }} বাংলাদেশ হলো</p>
            <div class="animate-pop mt-3 flex items-center gap-4 overflow-hidden rounded-3xl border border-line bg-card p-3 text-left shadow-xl">
                <img src="{{ $s['location']['illustration'] }}" alt="{{ $s['location']['name_bn'] }}-এর ছবি" width="400" height="300" fetchpriority="high" class="aspect-[4/3] w-28 shrink-0 rounded-2xl object-cover">
                <div class="min-w-0">
                    <div class="text-2xl font-bold text-accent">{{ $s['location']['emoji'] }} {{ $s['location']['name_bn'] }}</div>
                    <div class="truncate font-semibold">{{ $s['location']['title_bn'] }}</div>
                    <div class="mt-1.5 inline-flex rounded-full bg-flag-red/10 px-2.5 py-0.5 text-xs font-semibold text-flag-red">
                        ভাইব ম্যাচ {{ \App\Support\Bangla::digits($s['match_pct']) }}%
                    </div>
                </div>
            </div>

            <h1 class="mt-8 text-[2rem] leading-tight font-bold">আর তোমার<br>বাংলাদেশ কোথায়?</h1>
            <p class="mx-auto mt-3 max-w-sm text-lg leading-relaxed text-ink-2">
                খেলে দেখো, {{ $s['name'] ? $friend : 'তোমার বন্ধুর' }} সাথে তোমার মিল কত&nbsp;%&nbsp;👀
            </p>
            <button type="button" class="btn-primary group mt-6" @click="start()">
                আমার বাংলাদেশ খুঁজে দেখি <span aria-hidden="true" class="transition-transform duration-200 group-hover:translate-x-1">→</span>
            </button>
            <ul class="mt-4 flex flex-wrap justify-center gap-2" aria-label="কুইজের তথ্য">
                <li class="chip">⏱️ মাত্র ১ মিনিট</li>
                <li class="chip">✨ {{ \App\Support\Bangla::digits(count($boot['questions'])) }}টি প্রশ্ন</li>
                <li class="chip">🔒 লগইন লাগবে না</li>
            </ul>
            @if ($friendPlays > 0)
                <p class="mt-5 text-sm text-ink-2">👥 {{ \App\Support\Bangla::digits($friendPlays) }} জন বন্ধু এর মধ্যেই মিলিয়ে দেখেছে</p>
            @endif
        </section>
    @endif

    {{-- ===================== QUESTIONS ===================== --}}
    <section x-show="screen === 'question'" x-cloak class="screen h-dvh pb-[max(1rem,env(safe-area-inset-bottom))] md:max-w-2xl lg:max-w-4xl lg:pb-12"
        @keydown.window="screen === 'question' && onKey($event)" @touchstart.passive="swipeStart($event)" @touchend.passive="swipeEnd($event)">
        <header class="flex items-center gap-3 py-4">
            <button type="button" @click="back()" class="flex size-11 shrink-0 items-center justify-center rounded-full border border-line" aria-label="আগের প্রশ্ন">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="flex flex-1 gap-1.5" role="progressbar" aria-label="অগ্রগতি" :aria-valuenow="qIndex + 1" aria-valuemin="1" :aria-valuemax="questions.length">
                <template x-for="(q, i) in questions" :key="q.id">
                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full bg-flag-red transition-[width] duration-300 ease-out" :style="`width: ${i < qIndex || (i === qIndex && picked) ? 100 : (i === qIndex ? 35 : 0)}%`"></span></span>
                </template>
            </div>
            <span class="w-10 text-right text-sm font-semibold tabular-nums text-ink-2" x-text="`${bn(qIndex + 1)}/${bn(questions.length)}`"></span>
        </header>

        <template x-for="q in (screen === 'question' ? [question] : [])" :key="q.id">
            <div class="flex flex-1 flex-col lg:justify-center">
                <div class="flex flex-1 flex-col items-center justify-center py-4 text-center lg:flex-none lg:pb-12" :class="dir === 'back' ? 'animate-in-left' : 'animate-in-right'">
                    {{-- Floating answer emojis as the question's visual; the one being pointed at or picked lights up.
                         Image questions carry their own visuals. --}}
                    <div x-show="q.kind !== 'image'" class="relative mb-6 size-40 rounded-full bg-flag-green/10 transition-colors duration-300 lg:size-48 [@media(min-height:760px)]:size-48"
                        :class="(picked ?? hoverOpt) && 'bg-accent/15'" aria-hidden="true">
                        <template x-for="(o, i) in q.options" :key="o.id">
                            <span class="animate-pop absolute text-5xl [@media(min-height:760px)]:text-6xl" :style="`${['top:6%;left:8%', 'top:12%;right:4%', 'bottom:6%;left:14%', 'bottom:10%;right:10%'][i]}; animation-delay: ${i * 70}ms`">
                                <span class="animate-bob block" :style="`animation-delay: ${i * -0.7}s`">
                                    <span class="collage-emoji block" :class="{ 'is-lit': (picked ?? hoverOpt) === o.id, 'is-dim': (picked ?? hoverOpt) && (picked ?? hoverOpt) !== o.id }" x-text="o.emoji"></span>
                                </span>
                            </span>
                        </template>
                    </div>
                    <p x-show="nudge" class="animate-pop mb-3 rounded-full bg-accent/10 px-3 py-1 text-sm font-semibold text-accent" x-text="nudge"></p>
                    <h2 class="animate-rise text-[1.9rem] leading-snug font-bold lg:text-[2.6rem]" x-text="q.prompt" :id="`q-${q.id}`"></h2>
                    <p x-show="q.subtitle" class="mt-2 text-ink-2" x-text="q.subtitle"></p>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4" role="group" :aria-labelledby="`q-${q.id}`">
                    <template x-for="(o, i) in q.options" :key="o.id">
                        <button type="button" class="option animate-rise" :style="`animation-delay: ${80 + i * 50}ms`"
                            :class="{
                                'overflow-hidden !p-0 min-h-36 justify-end lg:min-h-48': q.kind === 'image',
                                'lg:min-h-36': q.kind !== 'image',
                                'scale-[1.04] shadow-xl': picked === o.id,
                                'scale-95 opacity-35': picked && picked !== o.id,
                            }"
                            :aria-pressed="(picked === o.id || answers[q.id] === o.id).toString()"
                            @pointerdown="ripple($event)" @mouseenter="hoverOpt = o.id" @mouseleave="hoverOpt = null" @focus="hoverOpt = o.id" @blur="hoverOpt = null"
                            @click="choose(o)">
                            <template x-if="q.kind === 'image' && o.image">
                                <img :src="o.image" alt="" class="absolute inset-0 size-full object-cover">
                            </template>
                            <span class="absolute top-2.5 left-3 hidden size-6 items-center justify-center rounded-lg border border-current/25 text-xs opacity-60 [@media(hover:hover)]:lg:flex"
                                :class="q.kind === 'image' && 'z-10 bg-black/40 text-white'" aria-hidden="true" x-text="bn(i + 1)"></span>
                            <span x-show="picked === o.id" class="animate-pop absolute top-2 right-2 z-10 flex size-7 items-center justify-center rounded-full bg-white text-sm font-bold text-accent shadow" aria-hidden="true">✓</span>
                            <span x-show="q.kind !== 'image'" class="text-4xl leading-none lg:text-5xl" aria-hidden="true" x-text="o.emoji"></span>
                            <span :class="q.kind === 'image' && 'relative w-full bg-gradient-to-t from-black/75 to-transparent px-2 pt-6 pb-2.5 text-white text-[0.95rem]'" x-text="o.label"></span>
                        </button>
                    </template>
                </div>
                <p class="mt-5 hidden text-center text-sm text-ink-2 [@media(hover:hover)]:lg:block">কীবোর্ডে ১–৪ চেপেও উত্তর দিতে পারো · ← আগের প্রশ্ন</p>
            </div>
        </template>
    </section>

    {{-- ===================== REVEAL ===================== --}}
    <section x-show="screen === 'reveal'" x-cloak class="screen h-dvh items-center justify-center text-center" aria-live="polite">
        <div class="relative w-[62%] max-w-60 md:max-w-72">
            @include('partials.bd-map', ['mode' => 'reveal', 'class' => 'w-full', 'locations' => $boot['locations']])
        </div>

        <ul class="mt-6 min-h-36 space-y-1.5 text-lg" x-show="!lockedSlug && !error">
            <template x-for="(step, i) in revealSteps" :key="i">
                <li x-show="revealStep >= i" class="animate-rise" :class="i === revealSteps.length - 1 ? 'font-bold' : 'text-ink-2'">
                    <span x-text="step"></span><span x-text="i < revealSteps.length - 1 ? ' ✓' : '…'"></span>
                </li>
            </template>
        </ul>

        <template x-if="lockedSlug && !error">
            <div class="animate-pop mt-6 min-h-36">
                <p class="text-ink-2">তোমার বাংলাদেশ…</p>
                <p class="mt-1 text-5xl font-bold" x-text="(() => { const l = locations.find(l => l.slug === lockedSlug); return l ? `${l.emoji} ${l.name_bn}` : '' })()"></p>
            </div>
        </template>

        <div x-show="error" class="mt-6 min-h-36">
            <p class="font-semibold" x-text="error"></p>
            <button type="button" class="btn-primary mt-4" @click="submit()">আবার চেষ্টা করি</button>
        </div>
    </section>

    {{-- ===================== RESULT ===================== --}}
    <template x-if="screen === 'result' && result">
        <section class="screen relative pb-12 md:max-w-2xl lg:grid lg:max-w-6xl lg:grid-cols-[5fr_6fr] lg:items-start lg:gap-x-14 lg:px-8">
            {{-- Brand bar: over the hero on phones, above the columns on desktop --}}
            <header class="absolute inset-x-0 top-0 z-10 flex items-center justify-between px-4 py-3 text-white lg:static lg:col-span-2 lg:px-0 lg:py-5 lg:text-ink">
                <a href="/" @click.prevent="history.pushState({}, '', '/'); screen = 'landing'; scrollTo(0, 0)" class="flex items-center gap-2 text-sm font-bold tracking-wide drop-shadow lg:drop-shadow-none"><span class="rounded-full bg-card p-0.5 lg:bg-transparent lg:p-0">@include('partials.logo', ['class' => 'block size-6'])</span>আমার বাংলাদেশ</a>
                <button type="button" @click="retake()" class="rounded-full bg-black/25 px-3 py-1.5 text-sm font-semibold backdrop-blur transition hover:bg-black/40 lg:border lg:border-line lg:bg-card lg:backdrop-blur-none lg:hover:bg-paper-2">🔁 আবার খেলি</button>
            </header>

            {{-- Phone/tablet hero; on desktop the live share card takes this place --}}
            <div class="relative -mx-4 overflow-hidden rounded-b-[2.5rem] bg-accent lg:hidden">
                <img :src="result.location.illustration" :alt="`${result.location.name_bn}-এর ছবি`" width="400" height="300" class="aspect-[4/3] w-full object-cover">
                <div class="absolute inset-x-0 top-0 h-1/4 bg-gradient-to-b from-black/45 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black/80 via-black/45 to-transparent"></div>
                <div class="absolute bottom-5 left-5 text-white">
                    <p class="transition-all" :class="liveName ? 'text-xl font-bold' : 'text-sm font-medium opacity-90'" x-text="liveName ? `${possessive(liveName)} বাংলাদেশ` : 'তোমার বাংলাদেশ'"></p>
                    <h1 class="animate-pop text-5xl leading-tight font-bold" x-text="`${result.location.emoji} ${result.location.name_bn}`"></h1>
                </div>
            </div>

            <aside class="hidden lg:sticky lg:top-6 lg:block">
                <button type="button" @click="openSheet()" class="group relative mx-auto block overflow-hidden rounded-[2rem] bg-paper-2 shadow-2xl ring-1 ring-line transition hover:-translate-y-1"
                    :class="cardSquare ? 'aspect-square w-full' : 'aspect-[9/16] h-[min(78dvh,40rem)]'" aria-label="কার্ডটা শেয়ার করো">
                    <template x-if="cardUrl"><img :src="cardUrl" alt="তোমার শেয়ার কার্ড" class="size-full object-cover transition-opacity" :class="cardBusy && 'opacity-70'"></template>
                    <span x-show="!cardUrl" class="absolute inset-0 animate-pulse bg-gradient-to-b from-paper-2 to-line"></span>
                    <span class="absolute inset-x-4 bottom-4 rounded-2xl bg-ink/85 px-4 py-3 text-center text-sm font-semibold text-paper opacity-0 backdrop-blur transition group-hover:opacity-100">🎨 ডিজাইন ও রং বদলাও · শেয়ার করো</span>
                </button>
                <p class="mt-3 text-center text-sm text-ink-2">এটাই তোমার শেয়ার কার্ড — নাম লিখলেই বদলে যাবে ✨</p>
            </aside>

            <div>
            {{-- Desktop headline (phones show it on the hero) --}}
            <div class="hidden lg:block">
                <p class="font-semibold text-ink-2" x-text="liveName ? `${possessive(liveName)} বাংলাদেশ` : 'তোমার বাংলাদেশ'"></p>
                <h1 class="animate-pop text-6xl leading-tight font-bold" x-text="`${result.location.emoji} ${result.location.name_bn}`"></h1>
            </div>

            {{-- Title + match --}}
            <div class="mt-6 flex items-center gap-4 lg:mt-4">
                <div class="flex-1">
                    <p class="text-2xl leading-snug font-bold text-accent lg:text-3xl" x-text="result.location.title_bn"></p>
                    <p class="mt-1 text-ink-2" x-text="result.location.tagline_bn"></p>
                </div>
                <div class="relative size-24 shrink-0" role="img" :aria-label="`ভাইব ম্যাচ ${result.match_pct} শতাংশ`">
                    <svg viewBox="0 0 100 100" class="size-full -rotate-90">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="var(--line)" stroke-width="9" />
                        <circle cx="50" cy="50" r="42" fill="none" stroke="var(--red)" stroke-width="9" stroke-linecap="round"
                            stroke-dasharray="263.9" :stroke-dashoffset="263.9 * (1 - shownPct / 100)" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl leading-none font-bold tabular-nums" x-text="`${bn(shownPct)}%`"></span>
                        <span class="text-[0.7rem] text-ink-2">ভাইব ম্যাচ</span>
                    </div>
                </div>
            </div>

            {{-- Your card: name + share in one block --}}
            <div class="mt-6 rounded-3xl border border-line bg-card p-4 shadow-[0_8px_30px_-12px_rgb(20_33_27/0.18)]">
                <div class="flex gap-4">
                    <button type="button" @click="openSheet()" :class="cardSquare ? 'aspect-square' : 'aspect-[9/16]'" class="relative w-20 shrink-0 self-start overflow-hidden rounded-xl bg-paper-2 shadow-md ring-1 ring-line transition active:scale-95 lg:hidden" aria-label="কার্ডটা দেখো">
                        <template x-if="cardUrl"><img :src="cardUrl" alt="" class="size-full object-cover"></template>
                        <span x-show="!cardUrl" class="absolute inset-0 animate-pulse bg-line"></span>
                    </button>
                    <div class="min-w-0 flex-1">
                        <template x-if="!result.name || nameEditing">
                            <form @submit.prevent="submitName()">
                                <label for="result-name" class="block text-lg leading-tight font-bold">✍️ কার্ডে তোমার নাম</label>
                                <p class="mt-0.5 text-sm text-ink-2">নাম দিলে বন্ধুরা কার্ডটা বেশি খোলে</p>
                                <div class="mt-2.5 flex gap-2">
                                    <input id="result-name" x-model="nameInput" maxlength="20" autocomplete="given-name" enterkeyhint="done" placeholder="তোমার নাম"
                                        class="min-h-12 min-w-0 flex-1 rounded-2xl border-2 border-line bg-paper px-3 text-lg outline-none transition focus:border-accent">
                                    <button type="submit" class="min-h-12 shrink-0 rounded-2xl bg-accent px-4 font-semibold text-white transition active:scale-95 disabled:opacity-50"
                                        :disabled="savingName" x-text="savingName ? '…' : 'বসাও'"></button>
                                </div>
                            </form>
                        </template>
                        <template x-if="result.name && !nameEditing">
                            <div class="flex h-full flex-col justify-center">
                                <p class="text-sm text-ink-2">তোমার কার্ড তৈরি 🎉</p>
                                <p class="text-lg font-bold"><span class="text-accent">✓</span> <span x-text="`${possessive(result.name)} বাংলাদেশ`"></span></p>
                                <button type="button" class="mt-1 self-start text-sm font-semibold text-ink-2 underline-offset-4 hover:text-ink hover:underline"
                                    @click="nameEditing = true; $nextTick(() => document.getElementById('result-name')?.focus())">✏️ নাম বদলাও</button>
                            </div>
                        </template>
                    </div>
                </div>

                <button type="button" class="btn-primary mt-4" @click="openSheet()">
                    কার্ডটা শেয়ার করো <span aria-hidden="true">✨</span>
                </button>
                <div class="mt-4 grid grid-cols-4 gap-2 border-t border-line pt-4">
                    <button type="button" class="share-btn" @click="shareTo('whatsapp')"><span class="bg-[#25D366]">@include('partials.icon', ['name' => 'whatsapp'])</span><span>WhatsApp</span></button>
                    <button type="button" class="share-btn" @click="shareTo('messenger')"><span class="bg-[#0084FF]">@include('partials.icon', ['name' => 'messenger'])</span><span>Messenger</span></button>
                    <button type="button" class="share-btn" @click="shareTo('facebook')"><span class="bg-[#1877F2]">@include('partials.icon', ['name' => 'facebook'])</span><span>Facebook</span></button>
                    <button type="button" class="share-btn" @click="copyLink()"><span class="bg-ink !text-paper">@include('partials.icon', ['name' => 'link'])</span><span>লিংক কপি</span></button>
                </div>
            </div>

            {{-- Friend comparison --}}
            <template x-if="result.friend">
                <div class="mt-4 flex items-center gap-4 rounded-3xl bg-flag-red/10 p-4">
                    <div class="text-4xl font-bold text-flag-red" x-text="`${bn(result.friend.pct)}%`"></div>
                    <div class="text-sm leading-relaxed">
                        <p class="font-semibold" x-text="`${result.friend.name ? possessive(result.friend.name) : 'তোমার বন্ধুর'} সাথে তোমার মিল`"></p>
                        <p class="text-ink-2" x-text="result.friend.same ? `দুজনেরই বাংলাদেশ ${result.friend.location.name_bn}! 🤝` : `ওর বাংলাদেশ ${result.friend.location.emoji} ${result.friend.location.name_bn}, তোমার ${result.location.name_bn}।`"></p>
                    </div>
                </div>
            </template>

            {{-- Why --}}
            <div class="mt-8">
                <h2 class="text-lg font-bold" x-text="`কেন ${result.location.name_bn}?`"></h2>
                <p class="mt-2 text-lg leading-relaxed font-medium" x-text="result.reason"></p>
                <p class="mt-3 leading-relaxed text-ink-2" x-text="result.location.description_bn"></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <template x-for="b in result.location.badges" :key="b">
                        <span class="chip" x-text="b"></span>
                    </template>
                </div>
            </div>

            {{-- Vibe traits --}}
            <div class="mt-8">
                <h2 class="text-lg font-bold">তোমার ভাইব</h2>
                <p class="text-sm text-ink-2">যেকোনোটায় ট্যাপ করে দেখো কোন উত্তর থেকে এলো 👆</p>
                <ul class="mt-3 space-y-1" x-init="observeBars($el)">
                    <template x-for="(t, i) in result.traits" :key="t.key">
                        <li>
                            <button type="button" class="trait-row" @click="toggleTrait(t.key)" :aria-expanded="(openTrait === t.key).toString()">
                                <span class="mb-1 flex justify-between text-sm font-semibold">
                                    <span x-text="`${t.emoji} ${t.label}`"></span>
                                    <span class="flex items-center gap-1.5 tabular-nums"><span x-text="`${bn(t.pct)}%`"></span><span class="text-ink-2 transition-transform duration-200" :class="openTrait === t.key && 'rotate-90'" aria-hidden="true">›</span></span>
                                </span>
                                <span class="block h-3 overflow-hidden rounded-full bg-paper-2">
                                    <span class="block h-full rounded-full bg-accent transition-[width] duration-700" :style="`width: ${barsIn ? t.pct : 0}%; transition-delay: ${i * 80}ms; opacity: ${1 - i * 0.12}`"></span>
                                </span>
                            </button>
                            <div x-show="openTrait === t.key" x-transition.opacity.duration.200ms class="px-1 pt-1 pb-2 text-sm">
                                <template x-if="traitAnswers(t).length">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-ink-2">এসেছে এখান থেকে:</span>
                                        <template x-for="o in traitAnswers(t)" :key="o.id"><span class="chip !py-1" x-text="`${o.emoji ?? ''} ${o.label}`"></span></template>
                                    </div>
                                </template>
                                <p x-show="!traitAnswers(t).length" class="text-ink-2">তোমার উত্তরে এটা খুব একটা আসেনি 🙂</p>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Runner-up --}}
            <template x-if="result.second">
                <button type="button" @click="openPlace(result.second.slug)" class="group mt-8 flex w-full items-center justify-between rounded-3xl border border-line bg-card p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md active:scale-[0.98]">
                    <div>
                        <p class="text-sm text-ink-2">তোমার দ্বিতীয় বাংলাদেশ</p>
                        <p class="text-xl font-bold" x-text="`${result.second.emoji} ${result.second.name_bn}`"></p>
                    </div>
                    <span class="flex items-center gap-2 text-lg font-semibold text-ink-2"><span x-text="`${bn(result.second.pct)}%`"></span><span class="text-2xl transition-transform group-hover:translate-x-1" aria-hidden="true">›</span></span>
                </button>
            </template>

            {{-- All places --}}
            <div class="mt-8">
                <h2 class="text-lg font-bold">সব বাংলাদেশ, তোমার সাথে কতটা মেলে</h2>
                <p class="text-sm text-ink-2">ট্যাপ করে দেখো কোন উত্তর কোথায় টেনেছে 👆</p>
                <div class="-mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pt-1 pb-2 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-5 md:overflow-visible md:px-0">
                    <template x-for="l in (places.length ? places : locations)" :key="l.slug">
                        <button type="button" @click="places.length && openPlace(l.slug)" class="place-card relative w-28 shrink-0 overflow-hidden rounded-2xl border bg-card text-left md:w-auto" :class="l.slug === result.location.slug ? 'border-accent border-2' : 'border-line'">
                            <img :src="l.illustration" alt="" loading="lazy" class="h-20 w-full object-cover">
                            <span x-show="l.pct" class="pill absolute top-1.5 right-1.5 bg-card/90 text-ink shadow-sm tabular-nums" x-text="`${bn(l.pct)}%`"></span>
                            <span class="block px-2 py-1.5 text-sm font-semibold" x-text="`${l.emoji} ${l.name_bn}`"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- After the result (never before it): what other Bangladeshis are asking. --}}
            <div class="mt-8 rounded-3xl border border-line bg-card p-4" x-data="communityPreview(2)">
                <h2 class="text-lg font-bold">🇧🇩 বাংলাদেশিরা এখন কী জিজ্ঞেস করছে</h2>
                <p class="text-sm text-ink-2">জানা থাকলে উত্তর দাও, না হলে নিজেই কিছু জিজ্ঞেস করো।</p>
                <div x-show="count" x-cloak class="mt-3 space-y-3" x-html="html"></div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('feed', [], false) }}" class="btn-ghost !px-2" @click="trackCommunity('result', 'feed')">💬 আলোচনা দেখো</a>
                    <a href="{{ route('ask', [], false) }}" class="btn-ghost !px-2" @click="trackCommunity('result', 'ask')">✍️ জিজ্ঞেস করো</a>
                </div>
            </div>

            <button type="button" class="btn-ghost mt-8 w-full" @click="retake()">🔁 আবার খেলি</button>
            <footer class="mt-8 border-t border-line pt-5 text-center text-xs leading-relaxed text-ink-2">
                <p>এটা মজার একটা ভাইব-ম্যাচ, বৈজ্ঞানিক পরীক্ষা না 🙂</p>
                <p class="mt-1">কোনো লগইন নেই · তোমার নাম, ফোন বা ইমেইল আমরা চাই না</p>
            </footer>
            </div>
        </section>
    </template>

    {{-- ===================== SHARE SHEET ===================== --}}
    <div x-show="sheetOpen" x-cloak class="fixed inset-0 z-40 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" aria-label="শেয়ার করো" @keydown.escape.window="sheetOpen = false">
        <div class="absolute inset-0 bg-black/50" x-show="sheetOpen" x-transition.opacity @click="sheetOpen = false"></div>
        <div x-show="sheetOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0" x-transition:leave="transition duration-200" x-transition:leave-end="translate-y-full md:translate-y-8 md:opacity-0"
            class="relative max-h-[94dvh] w-full max-w-md overflow-y-auto rounded-t-[2rem] bg-paper px-4 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] md:max-w-lg md:rounded-[2rem] md:px-6 md:pt-6 md:pb-6 md:shadow-2xl">
            <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-line md:hidden"></div>

            <form class="mb-4 md:pr-10" @submit.prevent="saveName()">
                <label for="card-name" class="mb-1.5 block font-bold">✍️ কার্ডে তোমার নাম</label>
                <div class="flex gap-2">
                    <input id="card-name" x-ref="sheetName" x-model="nameInput" maxlength="20" autocomplete="given-name" enterkeyhint="done" placeholder="তোমার নাম লেখো"
                        class="min-h-14 min-w-0 flex-1 rounded-2xl border-2 bg-card px-4 text-lg outline-none transition focus:border-accent"
                        :class="liveName ? 'border-line' : 'border-accent ring-4 ring-accent/20'">
                    <button type="submit" class="min-h-14 shrink-0 rounded-2xl bg-accent px-5 font-semibold text-white transition active:scale-95 disabled:opacity-50"
                        :disabled="savingName || liveName === (result?.name || '')" x-text="savingName ? '…' : (liveName && liveName === result?.name ? '✓' : 'বসাও')"></button>
                </div>
            </form>
            <button type="button" class="absolute top-4 right-4 hidden size-9 items-center justify-center rounded-full border border-line text-ink-2 md:flex" @click="sheetOpen = false" aria-label="বন্ধ করো">✕</button>

            {{-- Big card preview with the colour picker beside it --}}
            <div class="flex items-center justify-center gap-4">
                <div class="flex shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-paper-2 shadow-inner"
                    :class="cardSquare ? 'aspect-square w-[min(15rem,58vw)]' : 'aspect-[9/16] h-[40dvh] max-h-96'">
                    <template x-if="cardUrl"><img :src="cardUrl" alt="তোমার শেয়ার কার্ড" class="size-full object-contain transition-opacity" :class="cardBusy && 'opacity-60'"></template>
                    <span x-show="!cardUrl" class="text-sm text-ink-2">কার্ড বানাচ্ছি…</span>
                </div>

                <div role="radiogroup" aria-label="কার্ডের রং" class="flex flex-col gap-1">
                    <p class="mb-1 text-xs font-semibold text-ink-2">🌈 রং</p>
                    <template x-for="t in cardThemes" :key="t.key">
                        <button type="button" role="radio" :aria-checked="cardTheme === t.key" @click="pickTheme(t.key)"
                            class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1 text-left transition active:scale-95"
                            :class="cardTheme === t.key ? 'bg-card shadow-sm' : ''">
                            <span class="color-swatch" :class="cardTheme === t.key ? 'is-on' : ''" :style="swatch(t)"></span>
                            <span x-text="t.label" class="text-xs leading-tight font-semibold whitespace-nowrap" :class="cardTheme === t.key ? 'text-accent' : 'text-ink-2'"></span>
                        </button>
                    </template>
                </div>
            </div>
            <p x-show="inApp && cardUrl" class="mt-2 text-center text-xs text-ink-2">👆 ছবিটার ওপর চেপে ধরে রাখো, তারপর "Save image"</p>

            {{-- Card design + colour pickers: live previews of the player's own card --}}
            <div class="mt-4 flex items-center gap-2" role="radiogroup" aria-label="কার্ডের সাইজ">
                <p class="text-xs font-semibold text-ink-2">📐 সাইজ</p>
                <div class="flex flex-1 rounded-full bg-paper-2 p-1">
                    <template x-for="f in cardFormats" :key="f.key">
                        <button type="button" role="radio" :aria-checked="cardFormat === f.key" @click="pickFormat(f.key)"
                            class="flex-1 rounded-full px-3 py-1.5 text-xs font-semibold transition active:scale-95"
                            :class="cardFormat === f.key ? 'bg-card text-accent shadow-sm' : 'text-ink-2'">
                            <span x-text="f.key === 'square' ? `⬜ ${f.label} · ফিড পোস্ট` : `📱 ${f.label} · স্ট্যাটাস`"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-4" role="radiogroup" aria-label="কার্ডের ডিজাইন">
                <p class="mb-1.5 text-xs font-semibold text-ink-2">🎨 ডিজাইন বেছে নাও</p>
                <div class="grid grid-cols-4 gap-2">
                    <template x-for="t in cardTemplates" :key="t.key">
                        <button type="button" role="radio" :aria-checked="cardTemplate === t.key" :aria-label="t.label" @click="pickTemplate(t.key)" class="group flex flex-col items-center gap-1 transition active:scale-95">
                            <span class="card-thumb" :class="[cardTemplate === t.key ? 'is-on' : '', cardSquare ? 'is-square' : '']">
                                <img x-show="thumb(t.key, cardTheme)" :src="thumb(t.key, cardTheme)" alt="" class="size-full object-cover">
                                <span x-show="!thumb(t.key, cardTheme)" x-text="t.icon" class="text-xl" aria-hidden="true"></span>
                            </span>
                            <span x-text="t.label" class="text-xs leading-tight font-semibold" :class="cardTemplate === t.key ? 'text-accent' : 'text-ink-2'"></span>
                        </button>
                    </template>
                </div>
            </div>

            <button type="button" x-show="canNativeShare" class="btn-primary mt-4" @click="shareNative()" x-text="cardSquare ? '📲 পোস্টে দাও' : '📲 স্টোরি / স্ট্যাটাসে দাও'"></button>

            <div class="mt-4 grid grid-cols-5 gap-1">
                <button type="button" class="share-btn" @click="shareTo('whatsapp')"><span class="bg-[#25D366]">@include('partials.icon', ['name' => 'whatsapp'])</span><span>WhatsApp</span></button>
                <button type="button" class="share-btn" @click="shareTo('messenger')"><span class="bg-[#0084FF]">@include('partials.icon', ['name' => 'messenger'])</span><span>Messenger</span></button>
                <button type="button" class="share-btn" @click="shareTo('facebook')"><span class="bg-[#1877F2]">@include('partials.icon', ['name' => 'facebook'])</span><span>Facebook</span></button>
                <button type="button" class="share-btn" @click="copyLink()"><span class="bg-ink !text-paper">@include('partials.icon', ['name' => 'link'])</span><span>লিংক</span></button>
                <button type="button" class="share-btn" @click="saveCard()" :disabled="!cardUrl"><span class="bg-flag-green">@include('partials.icon', ['name' => 'download'])</span><span>সেভ</span></button>
            </div>
        </div>
    </div>

    {{-- ===================== PLACE SHEET ===================== --}}
    <div x-show="placeView" x-cloak class="fixed inset-0 z-40 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" :aria-label="placeView?.name_bn"
        @keydown.escape.window="placeSlug = null" @keydown.right.window="placeView && stepPlace(1)" @keydown.left.window="placeView && stepPlace(-1)">
        <div class="absolute inset-0 bg-black/50" x-show="placeView" x-transition.opacity @click="placeSlug = null"></div>
        <template x-if="placeView">
            <div x-show="placeView" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full md:translate-y-8 md:opacity-0"
                class="relative max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-t-[2rem] bg-paper pb-[max(1.25rem,env(safe-area-inset-bottom))] md:rounded-[2rem] md:shadow-2xl" :style="`--accent: ${placeView.accent}`">
                <div class="relative h-44 overflow-hidden rounded-t-[2rem] md:h-52">
                    <img :src="placeView.illustration" alt="" class="size-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                    <button type="button" class="absolute top-3 right-3 flex size-9 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur" @click="placeSlug = null" aria-label="বন্ধ করো">✕</button>
                    <span x-show="!placeView.preview" class="pill absolute top-3 left-3 bg-white/90 text-ink" x-text="placeView.isTop ? '🎉 এটাই তোমার বাংলাদেশ' : `তোমার তালিকায় ${bn(placeView.rank)} নম্বর`"></span>
                    <div class="absolute inset-x-4 bottom-3 text-white">
                        <p class="text-3xl font-bold" x-text="`${placeView.emoji} ${placeView.name_bn}`"></p>
                        <p class="font-semibold opacity-90" x-text="placeView.title_bn"></p>
                    </div>
                </div>

                <div class="px-5 pt-4">
                    <template x-if="!placeView.preview">
                        <div>
                            <div class="flex items-center justify-between text-sm font-semibold"><span>তোমার সাথে মিল</span><span class="text-lg tabular-nums text-accent" x-text="`${bn(placeView.pct)}%`"></span></div>
                            <div class="mt-1.5 h-3 overflow-hidden rounded-full bg-paper-2">
                                <div class="h-full rounded-full bg-accent transition-[width] duration-500" :style="`width: ${placeView.pct}%`"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="placeView.options.length">
                        <div class="mt-4">
                            <p class="text-sm font-semibold" x-text="placeView.isTop ? 'যে উত্তরগুলো তোমাকে এখানে আনলো' : 'তোমার যে উত্তরগুলো এদিকে টেনেছে'"></p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="o in placeView.options" :key="o.id"><span class="chip border-accent/40" x-text="`${o.emoji ?? ''} ${o.label}`"></span></template>
                            </div>
                        </div>
                    </template>

                    <p x-show="placeView.tagline_bn" class="mt-4 font-semibold" x-text="placeView.tagline_bn"></p>
                    <p x-show="placeView.description_bn" class="mt-1 leading-relaxed text-ink-2" x-text="placeView.description_bn"></p>

                    <div class="mt-5 flex items-center gap-2">
                        <button type="button" class="btn-ghost size-12 !px-0" @click="stepPlace(-1)" aria-label="আগের জায়গা">‹</button>
                        <button x-show="!placeView.preview" type="button" class="btn-ghost flex-1" @click="placeSlug = null; openSheet()">বন্ধুকে পাঠাও 😄</button>
                        <button x-show="placeView.preview" type="button" class="btn-primary !min-h-12 flex-1 !text-base" @click="placeSlug = null; start()">এটা কি তুমি? খেলে দেখো →</button>
                        <button type="button" class="btn-ghost size-12 !px-0" @click="stepPlace(1)" aria-label="পরের জায়গা">›</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed inset-x-4 bottom-6 z-50 mx-auto max-w-sm rounded-2xl bg-ink px-4 py-3 text-center text-sm font-medium text-paper shadow-xl" role="status" x-text="toast"></div>
</main>
@endsection
