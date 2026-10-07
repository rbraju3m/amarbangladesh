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
        <header class="flex items-center justify-between py-4">
            <span class="text-sm font-semibold tracking-wide">🇧🇩 আমার বাংলাদেশ</span>
        </header>

        <div class="lg:grid lg:flex-1 lg:grid-cols-2 lg:items-center lg:gap-16 lg:py-6">
            <div class="relative mx-auto -mb-4 w-[68%] max-w-64 lg:mb-0 lg:w-[78%] lg:max-w-sm">
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
                <h1 class="text-[2.6rem] leading-[1.15] font-bold tracking-tight lg:text-[4.2rem]">
                    তোমার <span class="text-green-text">বাংলাদেশ</span><br>কোথায়?
                </h1>
                <p class="mx-auto mt-3 max-w-xs text-lg leading-relaxed text-ink-2 lg:mx-0 lg:mt-5 lg:max-w-md lg:text-xl">
                    বাংলাদেশের কোন জায়গাটা তোমার personality-র সাথে সবচেয়ে বেশি মেলে?
                </p>

                <button type="button" class="btn-primary group mt-7 lg:w-auto lg:px-10" @click="start()">
                    আমার বাংলাদেশ খুঁজে দেখি <span aria-hidden="true" class="transition-transform duration-200 group-hover:translate-x-1">→</span>
                </button>

                <ul class="mt-5 flex flex-wrap justify-center gap-2 lg:justify-start" aria-label="কুইজের তথ্য">
                    <li class="chip">⏱️ মাত্র ১ মিনিট</li>
                    <li class="chip">✨ {{ \App\Support\Bangla::digits(count($boot['questions'])) }}টি প্রশ্ন</li>
                    <li class="chip">🇧🇩 {{ \App\Support\Bangla::digits(count($boot['locations'])) }}টি বাংলাদেশ</li>
                </ul>
                <p class="mt-6 hidden text-sm text-ink-2 lg:block">👈 ম্যাপের বিন্দুগুলোতে মাউস রাখো</p>
            </div>
        </div>

        <div class="mt-12">
            <h2 class="mb-3 text-center text-sm font-semibold text-ink-2">এদের মধ্যে একটা তুমি 👇</h2>
            <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pt-2 pb-3 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0 lg:grid-cols-9">
                @foreach ($boot['locations'] as $loc)
                    <button type="button" data-place class="place-card w-36 shrink-0 snap-start overflow-hidden rounded-2xl border border-line bg-card text-left md:w-auto"
                        :class="focusSlug === '{{ $loc['slug'] }}' && 'is-focus'"
                        @pointerenter="$event.pointerType === 'mouse' && (focusSlug = '{{ $loc['slug'] }}')"
                        @pointerleave="$event.pointerType === 'mouse' && (focusSlug = null)"
                        @click="focusSlug = '{{ $loc['slug'] }}'">
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

        <footer class="mt-auto pt-10 text-center text-xs leading-relaxed text-ink-2">
            কোনো লগইন নেই। তোমার নাম, ফোন বা ইমেইল আমরা চাই না।
        </footer>
    </section>

    {{-- ===================== TEASER (someone shared their result) ===================== --}}
    @if ($isShared)
        @php($s = $boot['shared'])
        <section x-show="screen === 'teaser'" class="screen justify-center py-8 text-center md:max-w-lg" style="--accent: {{ $s['location']['accent'] }}">
            <p class="text-lg text-ink-2">
                {{ $s['name'] ? \App\Support\Bangla::possessive($s['name']) : 'তোমার বন্ধুর' }} বাংলাদেশ
            </p>
            <div class="animate-pop mx-auto mt-4 w-full overflow-hidden rounded-[2rem] border border-line bg-card shadow-xl">
                <img src="{{ $s['location']['illustration'] }}" alt="{{ $s['location']['name_bn'] }}-এর ছবি" width="400" height="300" class="aspect-[4/3] w-full object-cover">
                <div class="px-5 py-5">
                    <div class="text-4xl font-bold text-accent">{{ $s['location']['emoji'] }} {{ $s['location']['name_bn'] }}</div>
                    <div class="mt-1 text-lg font-semibold">{{ $s['location']['title_bn'] }}</div>
                    <div class="mt-3 inline-flex rounded-full bg-flag-red/10 px-3 py-1 text-sm font-semibold text-flag-red">
                        ভাইব ম্যাচ {{ \App\Support\Bangla::digits($s['match_pct']) }}%
                    </div>
                </div>
            </div>

            <h1 class="mt-8 text-3xl font-bold">আর তোমার বাংলাদেশ কোথায়?</h1>
            <p class="mt-2 text-ink-2">{{ \App\Support\Bangla::digits(count($boot['questions'])) }}টা প্রশ্ন, ১ মিনিট। দেখো কতটা মেলে!</p>
            <button type="button" class="btn-primary mt-6" @click="start()">তোমার বাংলাদেশ খুঁজে দেখো <span aria-hidden="true">→</span></button>
        </section>
    @endif

    {{-- ===================== QUESTIONS ===================== --}}
    <section x-show="screen === 'question'" x-cloak class="screen h-dvh pb-[max(1rem,env(safe-area-inset-bottom))] md:max-w-2xl lg:max-w-4xl lg:pb-12"
        @keydown.window="screen === 'question' && onKey($event)">
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
                <div class="flex flex-1 flex-col items-center justify-center py-4 text-center lg:flex-none lg:pb-12">
                    {{-- Floating answer emojis as the question's visual; image questions carry their own visuals. --}}
                    <div x-show="q.kind !== 'image'" class="relative mb-6 size-40 rounded-full bg-flag-green/10 lg:size-48" aria-hidden="true">
                        <template x-for="(o, i) in q.options" :key="o.id">
                            <span class="animate-pop absolute text-5xl" :style="`${['top:6%;left:8%', 'top:12%;right:4%', 'bottom:6%;left:14%', 'bottom:10%;right:10%'][i]}; animation-delay: ${i * 70}ms`" x-text="o.emoji"></span>
                        </template>
                    </div>
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
        <section class="screen pb-12 md:max-w-2xl lg:grid lg:max-w-6xl lg:grid-cols-[5fr_6fr] lg:items-start lg:gap-12 lg:px-8">
            {{-- Hero (sticky beside the details on desktop) --}}
            <div class="relative -mx-4 overflow-hidden rounded-b-[2.5rem] bg-accent lg:sticky lg:top-8 lg:mx-0 lg:mt-8 lg:rounded-[2.5rem] lg:shadow-2xl">
                <img :src="result.location.illustration" :alt="`${result.location.name_bn}-এর ছবি`" width="400" height="300" class="aspect-[4/3] w-full object-cover">
                <div class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-black/80 via-black/45 to-transparent"></div>
                <div class="absolute bottom-5 left-5 text-white">
                    <p class="transition-all" :class="liveName ? 'text-xl font-bold' : 'text-sm font-medium opacity-90'" x-text="liveName ? `${possessive(liveName)} বাংলাদেশ` : 'তোমার বাংলাদেশ'"></p>
                    <h1 class="animate-pop text-5xl leading-tight font-bold" x-text="`${result.location.emoji} ${result.location.name_bn}`"></h1>
                </div>
            </div>

            <div class="lg:pt-8">
            {{-- Title + match --}}
            <div class="mt-6 flex items-center gap-4 lg:mt-0">
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

            {{-- Name for the card: prominent until set, then a one-line confirmation --}}
            <div class="mt-6 rounded-3xl border-2 p-4 transition-colors" :class="result.name && !nameEditing ? 'border-line bg-card' : 'border-accent bg-accent/5'">
                <template x-if="!result.name || nameEditing">
                    <form @submit.prevent="submitName()">
                        <label for="result-name" class="block text-lg font-bold">✍️ কার্ডে তোমার নাম বসাও</label>
                        <p class="mt-0.5 text-sm text-ink-2">নাম দিলে বন্ধুরা কার্ডটা বেশি খোলে</p>
                        <div class="mt-3 flex gap-2">
                            <input id="result-name" x-model="nameInput" maxlength="20" autocomplete="given-name" enterkeyhint="done" placeholder="তোমার নাম"
                                class="min-h-14 min-w-0 flex-1 rounded-2xl border-2 border-line bg-card px-4 text-lg outline-none focus:border-accent">
                            <button type="submit" class="min-h-14 shrink-0 rounded-2xl bg-accent px-5 font-semibold text-white transition active:scale-95 disabled:opacity-50"
                                :disabled="savingName" x-text="savingName ? '…' : 'বসাও ✓'"></button>
                        </div>
                    </form>
                </template>
                <template x-if="result.name && !nameEditing">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold"><span class="text-accent">✓</span> <span x-text="`${possessive(result.name)} বাংলাদেশ`"></span></p>
                        <button type="button" class="shrink-0 rounded-full px-2 py-1 text-sm font-semibold text-ink-2 hover:text-ink"
                            @click="nameEditing = true; $nextTick(() => document.getElementById('result-name')?.focus())">✏️ বদলাও</button>
                    </div>
                </template>
            </div>

            {{-- Share block, deliberately above the fold --}}
            <div class="mt-4 rounded-3xl border border-line bg-card p-4">
                <button type="button" class="btn-primary" @click="openSheet()">
                    কার্ডটা শেয়ার করো <span aria-hidden="true">✨</span>
                </button>
                <div class="mt-4 grid grid-cols-4 gap-2">
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
                <ul class="mt-3 space-y-3">
                    <template x-for="(t, i) in result.traits" :key="t.key">
                        <li>
                            <div class="mb-1 flex justify-between text-sm font-semibold"><span x-text="`${t.emoji} ${t.label}`"></span><span class="tabular-nums" x-text="`${bn(t.pct)}%`"></span></div>
                            <div class="h-3 overflow-hidden rounded-full bg-paper-2">
                                <div class="h-full rounded-full bg-accent transition-[width] duration-700" :style="`width: ${barsIn ? t.pct : 0}%; transition-delay: ${i * 80}ms; opacity: ${1 - i * 0.12}`"></div>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Runner-up --}}
            <template x-if="result.second">
                <div class="mt-8 flex items-center justify-between rounded-3xl border border-line bg-card p-4">
                    <div>
                        <p class="text-sm text-ink-2">তোমার দ্বিতীয় বাংলাদেশ</p>
                        <p class="text-xl font-bold" x-text="`${result.second.emoji} ${result.second.name_bn}`"></p>
                    </div>
                    <span class="text-lg font-semibold text-ink-2" x-text="`${bn(result.second.pct)}%`"></span>
                </div>
            </template>

            {{-- All places --}}
            <div class="mt-8">
                <h2 class="text-lg font-bold">বাকি বাংলাদেশগুলো</h2>
                <p class="text-sm text-ink-2">বন্ধুরা কোনটা পায়? পাঠিয়ে দেখো 😄</p>
                <div class="-mx-4 mt-3 flex gap-3 overflow-x-auto px-4 pt-1 pb-2 [scrollbar-width:none] md:mx-0 md:grid md:grid-cols-5 md:overflow-visible md:px-0">
                    <template x-for="l in locations" :key="l.slug">
                        <div class="place-card w-28 shrink-0 overflow-hidden rounded-2xl border bg-card md:w-auto" :class="l.slug === result.location.slug ? 'border-accent border-2' : 'border-line'">
                            <img :src="l.illustration" alt="" loading="lazy" class="h-20 w-full object-cover">
                            <div class="px-2 py-1.5 text-sm font-semibold" x-text="`${l.emoji} ${l.name_bn}`"></div>
                        </div>
                    </template>
                </div>
            </div>

            <button type="button" class="btn-ghost mt-8 w-full" @click="retake()">🔁 আবার খেলি</button>
            <p class="mt-6 text-center text-xs leading-relaxed text-ink-2">এটা মজার একটা ভাইব-ম্যাচ, বৈজ্ঞানিক পরীক্ষা না 🙂</p>
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

            <div class="mx-auto flex aspect-[9/16] max-h-[44dvh] items-center justify-center overflow-hidden rounded-2xl bg-paper-2 shadow-inner">
                <template x-if="cardUrl"><img :src="cardUrl" alt="তোমার শেয়ার কার্ড" class="h-full w-auto"></template>
                <span x-show="!cardUrl" class="text-sm text-ink-2">কার্ড বানাচ্ছি…</span>
            </div>
            <p x-show="inApp && cardUrl" class="mt-2 text-center text-xs text-ink-2">👆 ছবিটার ওপর চেপে ধরে রাখো, তারপর "Save image"</p>


            <button type="button" x-show="canNativeShare" class="btn-primary mt-4" @click="shareNative()">📲 স্টোরি / স্ট্যাটাসে দাও</button>

            <div class="mt-4 grid grid-cols-5 gap-1">
                <button type="button" class="share-btn" @click="shareTo('whatsapp')"><span class="bg-[#25D366]">@include('partials.icon', ['name' => 'whatsapp'])</span><span>WhatsApp</span></button>
                <button type="button" class="share-btn" @click="shareTo('messenger')"><span class="bg-[#0084FF]">@include('partials.icon', ['name' => 'messenger'])</span><span>Messenger</span></button>
                <button type="button" class="share-btn" @click="shareTo('facebook')"><span class="bg-[#1877F2]">@include('partials.icon', ['name' => 'facebook'])</span><span>Facebook</span></button>
                <button type="button" class="share-btn" @click="copyLink()"><span class="bg-ink !text-paper">@include('partials.icon', ['name' => 'link'])</span><span>লিংক</span></button>
                <button type="button" class="share-btn" @click="saveCard()" :disabled="!cardUrl"><span class="bg-flag-green">@include('partials.icon', ['name' => 'download'])</span><span>সেভ</span></button>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed inset-x-4 bottom-6 z-50 mx-auto max-w-sm rounded-2xl bg-ink px-4 py-3 text-center text-sm font-medium text-paper shadow-xl" role="status" x-text="toast"></div>
</main>
@endsection
