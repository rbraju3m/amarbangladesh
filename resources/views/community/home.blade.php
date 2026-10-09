@extends('community.layout', [
    'active' => 'home',
    'pageTitle' => __('আমার বাংলাদেশ — বাংলাদেশিদের প্রশ্ন আর উত্তর'),
    'ogTitle' => __('আমার বাংলাদেশ — বাংলাদেশিদের প্রশ্ন আর উত্তর'),
    'canonical' => lroute('home', [], true),
])

{{--
    Home: the community first. Phones: ask → what's new / needs an answer / solved → the map by
    division → topics → the quiz. Desktop: posts on the left; map, topics and quiz on the right.
--}}
@section('main')
<div class="mx-auto max-w-5xl px-4 pt-6 lg:grid lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-10">
    <div class="min-w-0">
        <h1 class="text-[1.9rem] leading-tight font-bold md:text-4xl">{{ __('বাংলাদেশিদের প্রশ্ন,') }}<br><span class="text-green-text">{{ __('বাংলাদেশিরাই উত্তর দেয়') }}</span></h1>
        <p class="mt-2 max-w-xl text-ink-2">{{ __('ডাক্তার, পড়াশোনা, চাকরি, সরকারি কাজ, ঘোরাঘুরি — যা জানতে চান জিজ্ঞেস করুন, জানা থাকলে উত্তর দিন।') }}</p>

        <a href="{{ lroute('ask', [], false) }}" class="ask-prompt mt-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-flag-red/10 text-xl" aria-hidden="true">✍️</span>
            <span class="flex-1 text-ink-2">{{ __('কী জানতে চান?') }}</span>
            <span class="pill bg-flag-red px-3 py-1.5 text-sm text-white">{{ __('জিজ্ঞেস করুন') }}</span>
        </a>

        {{-- What's new / needs an answer / solved: three short server-rendered lists, switched in place --}}
        <section class="mt-8" x-data="{ tab: 'latest' }" aria-label="{{ __('আলোচনা') }}">
            <div class="flex items-center gap-1 border-b border-line" role="tablist">
                @foreach (\App\Community\Feed::TABS as $key => $label)
                    <button type="button" role="tab" id="home-tab-{{ $key }}" aria-controls="home-list-{{ $key }}" class="feed-tab"
                        :class="tab === '{{ $key }}' && 'is-on'" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                        @if ($key === 'latest') aria-selected="true" @endif>{{ __($label) }}</button>
                @endforeach
            </div>
            @foreach ($lists as $key => $posts)
                <div id="home-list-{{ $key }}" role="tabpanel" aria-labelledby="home-tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if ($key !== 'latest') x-cloak @endif class="mt-4">
                    <div class="space-y-3">
                        @forelse ($posts as $post)
                            @include('community.partials.post-card', ['post' => $post, 'compact' => true])
                        @empty
                            <p class="rounded-3xl border border-dashed border-line px-5 py-8 text-center text-ink-2">{{ __('এখানে এখনো কোনো পোস্ট নেই') }}</p>
                        @endforelse
                    </div>
                    <a href="{{ lroute('feed', $key === 'latest' ? [] : ['tab' => $key], false) }}" class="btn-ghost mt-4 w-full">{{ __('সব দেখুন →') }}</a>
                </div>
            @endforeach
        </section>
    </div>

    <aside class="mt-10 space-y-6 lg:sticky lg:top-20 lg:mt-0">
        {{-- The map by division. Without JavaScript every spot and chip is a link to that division's feed. --}}
        <section class="rounded-[2rem] border border-line bg-card p-5" x-data="divisionMap(@js(collect($spots)->mapWithKeys(fn ($s) => [$s['slug'] => ['name' => __(':name বিভাগ', ['name' => $s['name']]), 'url' => lroute('feed', ['area' => $s['slug']], false)]])))" aria-labelledby="map-title">
            <h2 id="map-title" class="text-lg font-bold">{{ __('কোন এলাকায় কী নিয়ে কথা হচ্ছে?') }}</h2>
            <p class="text-sm text-ink-2">{{ __('ম্যাপে একটা বিভাগ বেছে নিন') }}</p>

            <div class="relative mx-auto mt-4 w-[min(62%,15rem)]">
                @include('partials.bd-map', ['mode' => 'plain', 'class' => 'w-full drop-shadow-[0_14px_24px_rgb(0_106_78/0.22)]', 'locations' => []])
                @foreach ($spots as $s)
                    <a href="{{ lroute('feed', ['area' => $s['slug']], false) }}" class="map-spot" style="left: {{ $s['x'] }}%; top: {{ $s['y'] }}%"
                        :class="spot === '{{ $s['slug'] }}' && 'is-on'" :aria-pressed="spot === '{{ $s['slug'] }}'" @click.prevent="pick('{{ $s['slug'] }}')"
                        aria-label="{{ __(':name বিভাগ', ['name' => $s['name']]) }}: {{ \App\Support\Lang::choice(':nটি আলোচনা', $s['count']) }}">{{ \App\Support\Lang::num($s['count']) }}</a>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap justify-center gap-1.5">
                @foreach ($spots as $s)
                    <a href="{{ lroute('feed', ['area' => $s['slug']], false) }}" class="filter-chip !text-xs" :class="spot === '{{ $s['slug'] }}' && 'is-on'" @click.prevent="pick('{{ $s['slug'] }}')">{{ $s['name'] }}</a>
                @endforeach
            </div>

            {{-- The picked division's latest posts --}}
            <div x-show="spot" x-cloak class="mt-5 border-t border-line pt-4" aria-live="polite">
                <p class="font-bold" x-text="name"></p>
                <div x-show="state === 'loading'" class="mt-3 space-y-2" aria-hidden="true"><div class="h-16 animate-pulse rounded-2xl bg-paper-2"></div><div class="h-16 animate-pulse rounded-2xl bg-paper-2"></div></div>
                <div x-show="state === 'ready'" class="mt-3 space-y-2" x-html="html"></div>
                <p x-show="state === 'empty'" class="mt-2 text-sm text-ink-2">{{ __('এই বিভাগে এখনো কোনো আলোচনা নেই। প্রথম প্রশ্নটা আপনিই করুন!') }}</p>
                <p x-show="state === 'error'" class="mt-2 text-sm text-danger">{{ __('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a :href="feedUrl" class="btn-ghost !min-h-11 !px-2 text-sm">{{ __('সব আলোচনা দেখুন') }}</a>
                    <a href="{{ lroute('ask', [], false) }}" class="btn-ghost !min-h-11 !px-2 text-sm">{{ __('জিজ্ঞেস করুন') }}</a>
                </div>
            </div>
        </section>

        <section aria-labelledby="topics-title">
            <h2 id="topics-title" class="text-lg font-bold">{{ __('বিষয় অনুযায়ী') }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-2">
                @foreach ($categories as $c)
                    <a href="{{ lroute('feed', ['category' => $c['slug']], false) }}" class="flex min-h-12 items-center gap-2 rounded-2xl border border-line bg-card px-3 text-sm font-semibold transition hover:border-ink-2">
                        <span class="text-lg" aria-hidden="true">{{ $c['emoji'] }}</span><span class="min-w-0 truncate">{{ $c['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        @include('community.partials.quiz-promo')
    </aside>
</div>
@endsection
