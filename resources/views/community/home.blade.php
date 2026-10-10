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
<div class="mx-auto flex max-w-5xl flex-col px-4 pt-6 lg:grid lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-10 lg:pt-10" x-data="divisionMap(@js(collect($spots)->mapWithKeys(fn ($s) => [$s['slug'] => ['name' => __(':name বিভাগ', ['name' => $s['name']]), 'url' => lroute('feed', ['area' => $s['slug']], false)]])))">
    <div class="contents lg:block lg:min-w-0">
        <div class="home-hero order-1">
            {{-- Flag motif: a soft green field and the red sun behind the heading --}}
            <span class="home-glow" aria-hidden="true"></span><span class="home-sun" aria-hidden="true"></span>
            <p class="home-eyebrow home-in"><span class="mini-flag" aria-hidden="true"></span>{{ __('৬৪ জেলার মানুষের প্রশ্ন-উত্তর') }}</p>
            <h1 class="home-in mt-3 text-[1.9rem] leading-tight font-bold md:text-[2.6rem]">{{ __('বাংলাদেশিদের প্রশ্ন,') }}<br><span class="text-green-text">{{ __('বাংলাদেশিরাই উত্তর দেয়') }}</span></h1>
            <p class="home-in mt-2 max-w-xl text-ink-2 [animation-delay:80ms]">{{ __('ডাক্তার, পড়াশোনা, চাকরি, সরকারি কাজ, ঘোরাঘুরি — যা জানতে চান জিজ্ঞেস করুন, জানা থাকলে উত্তর দিন।') }}</p>

            <a href="{{ lroute('ask', [], false) }}" class="ask-prompt home-in mt-5 [animation-delay:160ms]">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-flag-red/10 text-xl" aria-hidden="true">✍️</span>
                <span class="flex-1 text-ink-2">{{ __('কী জানতে চান?') }}</span>
                <span class="pill bg-flag-red px-3 py-1.5 text-sm text-white">{{ __('জিজ্ঞেস করুন') }}</span>
            </a>
            <ul class="home-in mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-2 [animation-delay:240ms]">
                <li><span class="text-green-text" aria-hidden="true">✓</span> {{ __('পড়তে লগইন লাগে না') }}</li>
                <li><span class="text-green-text" aria-hidden="true">✓</span> {{ __('চাইলে বেনামে জিজ্ঞেস করুন') }}</li>
                <li><span class="text-green-text" aria-hidden="true">✓</span> {{ __('একদম ফ্রি') }}</li>
            </ul>
        </div>

        <div class="order-3 mt-4 lg:mt-6" x-ref="posts">
            @include('community.partials.division-posts')
        </div>

        <div class="order-4 mt-10 lg:mt-8">
            {{-- What's new / needs an answer / solved: three short server-rendered lists, switched in place --}}
            <section x-data="{ tab: 'latest' }" aria-labelledby="lists-title">
                <h2 id="lists-title" class="mb-3 flex items-center gap-2 text-lg font-bold"><span class="live-dot" aria-hidden="true"></span>{{ __('এখন যা নিয়ে কথা হচ্ছে') }}</h2>
                <div class="flex items-center gap-1 border-b border-line" role="tablist">
                    @foreach (\App\Community\Feed::TABS as $key => $label)
                        <button type="button" role="tab" id="home-tab-{{ $key }}" aria-controls="home-list-{{ $key }}" class="feed-tab"
                            :class="tab === '{{ $key }}' && 'is-on'" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                            @if ($key === 'latest') aria-selected="true" @endif>{{ __($label) }}</button>
                    @endforeach
                </div>
                @foreach ($lists as $key => $posts)
                    <div id="home-list-{{ $key }}" role="tabpanel" aria-labelledby="home-tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if ($key !== 'latest') x-cloak @endif class="mt-4">
                        <div class="home-cards space-y-3">
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
    </div>

    <div class="contents lg:block lg:space-y-6">
        <section class="map-card order-2 mt-6 lg:mt-0" aria-labelledby="map-title">
            <h2 id="map-title" class="text-lg font-bold">{{ __('কোন এলাকায় কী নিয়ে কথা হচ্ছে?') }}</h2>
            <p class="mb-4 text-sm text-ink-2"><span x-show="!zoomed">{{ __('ম্যাপে একটা বিভাগ বেছে নিন') }}</span><span x-show="zoomed" x-cloak>{{ __('এবার একটা জেলা বেছে নিন') }}</span></p>
            @include('community.partials.division-map', ['spots' => $spots])
        </section>

        <aside class="order-5 mt-10 space-y-6 lg:mt-0">
            <section aria-labelledby="topics-title">
                <h2 id="topics-title" class="text-lg font-bold">{{ __('বিষয় অনুযায়ী') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($categories as $c)
                        <a href="{{ lroute('feed', ['category' => $c['slug']], false) }}" class="topic-tile">
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
