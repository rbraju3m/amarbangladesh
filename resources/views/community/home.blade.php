@extends('community.layout', [
    'active' => 'home',
    'pageTitle' => __('আমার বাংলাদেশ — বাংলাদেশিদের প্রশ্ন আর উত্তর'),
    'ogTitle' => __('আমার বাংলাদেশ — বাংলাদেশিদের প্রশ্ন আর উত্তর'),
    'canonical' => lroute('home', [], true),
])

@section('main')
{{--
    Home: the community first, with Bangladesh at its heart.
    Phones (one column, in this order): text and ask box, the map, the picked division's posts, the lists,
    topics, the quiz. Desktop: two columns that each flow on their own, so the tall map never leaves a gap
    beside the text: left = text, ask box, picked division, lists; right = map, topics, quiz.
    On phones both columns are `contents`, so their children are ordered with `order-*`.
--}}
<div class="mx-auto flex max-w-5xl flex-col px-4 pt-6 lg:grid lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-10 lg:pt-10" x-data="divisionMap(@js(collect($spots)->mapWithKeys(fn ($s) => [$s['slug'] => ['name' => __(':name বিভাগ', ['name' => $s['name']]), 'url' => lroute('feed', ['area' => $s['slug']], false), 'x' => $s['x'], 'y' => $s['y']]])), @js($lens), @js(collect($categories)->map(fn ($c) => $c['emoji'].' '.$c['name'])))">
    <div class="contents lg:block lg:min-w-0">
        <div class="home-hero order-1">
            {{-- Flag motif: a soft green field and the red sun behind the heading --}}
            <span class="home-glow" aria-hidden="true"></span><span class="home-sun" aria-hidden="true"></span>
            <p class="home-eyebrow home-in"><span class="mini-flag" aria-hidden="true"></span>{{ __('৬৪ জেলার মানুষের প্রশ্ন-উত্তর') }}</p>
            <h1 class="home-in mt-3 text-[1.9rem] leading-tight font-bold md:text-[2.6rem]">{{ __('বাংলাদেশিদের প্রশ্ন,') }}<br><span class="text-green-text">{{ __('বাংলাদেশিরাই উত্তর দেয়') }}</span></h1>
            <p class="home-in mt-2 max-w-xl text-ink-2 [animation-delay:80ms]">{{ __('ডাক্তার, পড়াশোনা, চাকরি, সরকারি কাজ, ঘোরাঘুরি — যা জানতে চান জিজ্ঞেস করুন, জানা থাকলে উত্তর দিন।') }}</p>

            {{-- Ask right here: a GET form to /ask (works without JS) that fills the title; while typing,
                 questions already asked show up (the Ask page's similarQuestions) --}}
            <div x-data="{ title: '' }" class="home-in mt-5 [animation-delay:160ms]">
                <form action="{{ lroute('ask', [], false) }}" method="get" class="ask-prompt" @submit="track('community_clicked', { meta: { from: 'home_ask', typed: title.trim() ? 1 : 0 } })">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-flag-red/10 text-xl" aria-hidden="true">✍️</span>
                    <label for="home-ask" class="sr-only">{{ __('কী জানতে চান?') }}</label>
                    <input id="home-ask" name="title" x-model="title" maxlength="200" autocomplete="off" enterkeyhint="go" placeholder="{{ __('কী জানতে চান?') }}" class="min-w-0 flex-1 bg-transparent py-2 text-base text-ink placeholder:text-ink-2 focus:outline-none">
                    <button class="pill shrink-0 bg-flag-red px-3 py-1.5 text-sm text-white">{{ __('জিজ্ঞেস করুন') }}</button>
                </form>
                <div x-data="similarQuestions" x-effect="lookup(title)" x-show="count" x-cloak class="mt-3 rounded-2xl border border-warn/30 bg-warn/5 p-3 text-left" aria-live="polite">
                    <p class="text-sm font-bold">{{ __('এমন প্রশ্ন আগে হয়েছে কি না দেখে নিন') }}</p>
                    <div x-html="html" @click="$event.target.closest('a') && track('similar_clicked', { meta: { from: 'home' } })"></div>
                </div>
            </div>
            <ul class="home-in mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-2 [animation-delay:240ms]">
                <li><span class="text-green-text" aria-hidden="true">✓</span> {{ __('পড়তে লগইন লাগে না') }}</li>
                <li><span class="text-green-text" aria-hidden="true">✓</span> {{ __('চাইলে বেনামে জিজ্ঞেস করুন') }}</li>
                <li><span class="text-green-text" aria-hidden="true">✓</span> {{ __('একদম ফ্রি') }}</li>
            </ul>

            {{-- The community in three numbers, counting up once on load --}}
            <dl class="home-in home-stats [animation-delay:300ms]">
                @foreach (['posts' => 'প্রশ্ন ও আলোচনা', 'answers' => 'উত্তর দেওয়া হয়েছে', 'solved' => 'সমাধান পেয়েছে'] as $key => $label)
                    <div><dt class="text-xs text-ink-2">{{ __($label) }}</dt><dd class="text-2xl font-bold tabular-nums" x-data="countUp({{ $stats[$key] }})" x-text="shown">{{ \App\Support\Lang::num($stats[$key]) }}</dd></div>
                @endforeach
            </dl>

            {{-- Happening now: the newest posts, one at a time (pauses under the pointer or focus) --}}
            @if ($ticker->isNotEmpty())
                <div class="home-in home-ticker [animation-delay:360ms]" x-data="ticker({{ $ticker->count() }})" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
                    <span class="live-dot shrink-0" aria-hidden="true"></span>
                    <span class="shrink-0 text-xs font-bold text-red-text">{{ __('এখন') }}</span>
                    <ul class="relative h-6 min-w-0 flex-1 overflow-hidden" aria-label="{{ __('নতুন পোস্ট') }}">
                        @foreach ($ticker as $i => $p)
                            <li class="ticker-item" x-show="i === {{ $i }}" @if ($i) x-cloak @endif
                                x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                                x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="-translate-y-full opacity-0">
                                <a href="{{ lroute('posts.show', ['post' => $p->id], false) }}" class="block truncate text-sm hover:underline">
                                    <span class="text-ink-2">{{ \App\Support\Lang::ago($p->created_at) }}@if ($p->area) · {{ $p->area->name() }}@endif ·</span>
                                    <span aria-hidden="true">{{ \App\Models\Post::TYPES[$p->type]['emoji'] ?? '' }}</span> <span class="font-semibold">{{ $p->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="order-3 mt-4 lg:mt-6" x-ref="posts">
            @include('community.partials.division-posts')
        </div>

        <div class="order-4 mt-10 lg:mt-8">
            {{-- What's new / needs an answer / solved: three short server-rendered lists, switched in place.
                 A topic (map lens or a topic tile) swaps in that topic's lists from /api/feed. --}}
            <section x-data="homeLists" aria-labelledby="lists-title" class="scroll-mt-20" id="lists">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 id="lists-title" class="flex items-center gap-2 text-lg font-bold"><span class="live-dot" aria-hidden="true"></span>{{ __('এখন যা নিয়ে কথা হচ্ছে') }}</h2>
                    <button type="button" x-show="topic" x-cloak class="filter-chip is-on !text-xs" @click="setTopic('')" :aria-label="t('বিষয় বাদ দিন: :name', { name: topicName })"><span x-text="topicName"></span> ✕</button>
                </div>
                <div class="flex items-center gap-1 border-b border-line" role="tablist">
                    @foreach (\App\Community\Feed::TABS as $key => $label)
                        <button type="button" role="tab" id="home-tab-{{ $key }}" aria-controls="home-list-{{ $key }}" class="feed-tab"
                            :class="tab === '{{ $key }}' && 'is-on'" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                            @if ($key === 'latest') aria-selected="true" @endif>{{ __($label) }}</button>
                    @endforeach
                </div>
                @foreach ($lists as $key => $posts)
                    <div id="home-list-{{ $key }}" role="tabpanel" aria-labelledby="home-tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if ($key !== 'latest') x-cloak @endif class="mt-4">
                        <template x-if="topic">
                            <div class="space-y-3 transition-opacity" :class="listState === 'loading' && 'opacity-60'">
                                <div x-show="filtered[key] === undefined && listState === 'slow'" class="space-y-3" aria-hidden="true"><div class="h-24 animate-pulse rounded-3xl bg-paper-2"></div><div class="h-24 animate-pulse rounded-3xl bg-paper-2"></div></div>
                                <div x-show="filtered[key]" x-html="filtered[key]" class="space-y-3"></div>
                                <p x-show="filtered[key] === ''" class="rounded-3xl border border-dashed border-line px-5 py-8 text-center text-ink-2">{{ __('এখানে এখনো কোনো পোস্ট নেই') }}</p>
                                <p x-show="filtered[key] === null" class="text-sm text-danger">{{ __('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।') }}</p>
                            </div>
                        </template>
                        <div class="home-cards space-y-3" x-show="!topic">
                            @forelse ($posts as $post)
                                @include('community.partials.post-card', ['post' => $post, 'compact' => true])
                            @empty
                                <p class="rounded-3xl border border-dashed border-line px-5 py-8 text-center text-ink-2">{{ __('এখানে এখনো কোনো পোস্ট নেই') }}</p>
                            @endforelse
                        </div>
                        <a href="{{ lroute('feed', $key === 'latest' ? [] : ['tab' => $key], false) }}" :href="moreUrl('{{ lroute('feed', $key === 'latest' ? [] : ['tab' => $key], false) }}')" class="btn-ghost mt-4 w-full">{{ __('সব দেখুন →') }}</a>
                    </div>
                @endforeach
            </section>
        </div>
    </div>

    <div class="contents lg:block lg:space-y-6">
        <section class="map-card order-2 mt-6 lg:mt-0" aria-labelledby="map-title">
            <h2 id="map-title" class="text-lg font-bold">{{ __('কোন এলাকায় কী নিয়ে কথা হচ্ছে?') }}</h2>
            <p class="mb-4 text-sm text-ink-2"><span x-show="!zoomed">{{ __('ম্যাপে একটা বিভাগ বেছে নিন') }}</span><span x-show="zoomed" x-cloak>{{ __('এবার একটা জেলা বেছে নিন') }}</span></p>
            {{-- Topic lens: re-shades the map (and the picked area's posts) for one topic --}}
            <div class="topic-lens -mx-1 mb-3 flex gap-1.5 overflow-x-auto px-1 pb-1" role="group" aria-label="{{ __('বিষয় দিয়ে ম্যাপ দেখুন') }}">
                <a href="{{ lroute('feed', [], false) }}" class="filter-chip shrink-0 !text-xs" :class="!topic && 'is-on'" :aria-pressed="!topic" @click.prevent="setTopic('')">{{ __('সব বিষয়') }}</a>
                @foreach ($categories as $c)
                    <a href="{{ lroute('feed', ['category' => $c['slug']], false) }}" class="filter-chip shrink-0 !text-xs" :class="topic === '{{ $c['slug'] }}' && 'is-on'" :aria-pressed="topic === '{{ $c['slug'] }}'" @click.prevent="setTopic('{{ $c['slug'] }}')"><span aria-hidden="true">{{ $c['emoji'] }}</span> {{ $c['name'] }}</a>
                @endforeach
            </div>
            @include('community.partials.division-map', ['spots' => $spots])
        </section>

        <aside class="order-5 mt-10 space-y-6 lg:mt-0">
            <section aria-labelledby="topics-title">
                <h2 id="topics-title" class="text-lg font-bold">{{ __('বিষয় অনুযায়ী') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($categories as $c)
                        <a href="{{ lroute('feed', ['category' => $c['slug']], false) }}" class="topic-tile" :class="topic === '{{ $c['slug'] }}' && 'is-on'" :aria-pressed="topic === '{{ $c['slug'] }}'" @click.prevent="setTopic('{{ $c['slug'] }}'); $nextTick(() => document.getElementById('lists').scrollIntoView({ behavior: 'smooth', block: 'start' }))">
                            <span class="topic-emoji" aria-hidden="true">{{ $c['emoji'] }}</span><span class="min-w-0 truncate">{{ $c['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            @include('community.partials.quiz-promo')
        </aside>
    </div>
</div>
@endsection
