@extends('community.layout', [
    'active' => 'feed',
    'pageTitle' => ($feed->area ? $feed->area['name'].'-এর আলোচনা' : ($feed->category ? $feed->category['name'].' — আলোচনা' : 'আলোচনা')).' · আমার বাংলাদেশ',
    'ogTitle' => 'বাংলাদেশে এখন মানুষ কী নিয়ে কথা বলছে?',
    'canonical' => route('feed', $feed->query()),
])

@section('main')
<div class="mx-auto max-w-5xl px-4 pt-5 lg:grid lg:grid-cols-[1fr_18rem] lg:gap-10">
    <div class="min-w-0">
        <h1 class="text-[1.7rem] leading-tight font-bold md:text-3xl">
            @if ($feed->area)
                📍 {{ $feed->area['name'] }}-এর মানুষ কী বলছে
            @elseif ($feed->category)
                {{ $feed->category['emoji'] }} {{ $feed->category['name'] }}
            @else
                বাংলাদেশে এখন কী নিয়ে কথা হচ্ছে
            @endif
        </h1>
        <p class="mt-1 text-ink-2">প্রশ্ন, উত্তর আর অভিজ্ঞতা — বাংলাদেশিদের কাছ থেকে, বাংলাদেশিদের জন্য।</p>

        {{-- Ask prompt: looks like an input, opens the composer --}}
        <a href="{{ route('ask', array_filter(['category' => $feed->category['slug'] ?? null, 'area' => $feed->area['slug'] ?? null]), false) }}" class="ask-prompt mt-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-flag-red/10 text-xl" aria-hidden="true">✍️</span>
            <span class="flex-1 text-ink-2">কী জানতে চাও?</span>
            <span class="pill bg-flag-red px-3 py-1.5 text-sm text-white">জিজ্ঞেস করো</span>
        </a>

        {{-- Tabs + filters --}}
        <div class="sticky top-[3.3rem] z-20 -mx-4 mt-5 border-b border-line bg-paper/95 px-4 pt-1 backdrop-blur">
            <div class="flex items-center gap-1">
                @foreach (\App\Community\Feed::TABS as $key => $label)
                    <a href="{{ route('feed', $feed->query(['tab' => $key === 'latest' ? null : $key]), false) }}" @class(['feed-tab', 'is-on' => $feed->tab === $key])>{{ $label }}</a>
                @endforeach
                <form method="get" action="{{ route('feed', [], false) }}" class="ml-auto">
                    @foreach ($feed->query(['area' => null]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <label for="area-filter" class="sr-only">এলাকা</label>
                    <select id="area-filter" name="area" class="area-select" onchange="this.form.submit()">
                        <option value="">📍 সারা দেশ</option>
                        <optgroup label="বিভাগ">
                            @foreach ($divisions as $d)
                                <option value="{{ $d['slug'] }}" @selected(($feed->area['slug'] ?? null) === $d['slug'])>{{ $d['name'] }} বিভাগ</option>
                            @endforeach
                        </optgroup>
                        @foreach ($districts as $division => $list)
                            <optgroup label="{{ $division }}">
                                @foreach ($list as $a)
                                    <option value="{{ $a['slug'] }}" @selected(($feed->area['slug'] ?? null) === $a['slug'])>{{ $a['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <noscript><button class="text-sm font-semibold">দেখাও</button></noscript>
                </form>
            </div>
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 py-2.5 [scrollbar-width:none]">
                <a href="{{ route('feed', $feed->query(['category' => null]), false) }}" @class(['filter-chip', 'is-on' => ! $feed->category])>সব বিষয়</a>
                @foreach ($categories as $c)
                    <a href="{{ route('feed', $feed->query(['category' => $c['slug']]), false) }}" @class(['filter-chip', 'is-on' => ($feed->category['slug'] ?? null) === $c['slug']])>{{ $c['emoji'] }} {{ $c['name'] }}</a>
                @endforeach
            </div>
        </div>

        <div id="feed-list" class="mt-4 space-y-3">
            @include('community.partials.post-list', ['posts' => $posts, 'promoAt' => 3])
        </div>

        @if ($posts->isEmpty())
            <div class="mt-4 rounded-3xl border border-dashed border-line px-6 py-10 text-center">
                <p class="text-4xl" aria-hidden="true">{{ $feed->isFiltered() ? '🔎' : '🌱' }}</p>
                <p class="mt-3 text-lg font-bold">{{ $feed->isFiltered() ? 'এখানে এখনো কোনো পোস্ট নেই' : 'আলোচনা সবে শুরু হচ্ছে' }}</p>
                <p class="mx-auto mt-1 max-w-sm text-ink-2">
                    {{ $feed->isFiltered() ? 'প্রথম প্রশ্নটা তুমিই করো, এই বিষয়ের মানুষ উত্তর দেবে।' : 'প্রথম প্রশ্নটা তুমিই করো। যা জানতে চাও, যে সমস্যার সমাধান খুঁজছো।' }}
                </p>
                <a href="{{ route('ask', array_filter(['category' => $feed->category['slug'] ?? null, 'area' => $feed->area['slug'] ?? null]), false) }}" class="btn-primary mt-5 !w-auto">✍️ প্রথম প্রশ্নটা করি</a>
                @if ($feed->isFiltered())
                    <p class="mt-4"><a href="{{ route('feed', [], false) }}" class="text-sm font-semibold text-ink-2 underline underline-offset-4">সব আলোচনা দেখো</a></p>
                @endif
            </div>
        @endif

        @if ($posts->nextPageUrl())
            <a href="{{ route('feed', $feed->query(['cursor' => $posts->nextCursor()->encode()]), false) }}" class="btn-ghost mt-5 w-full"
                @click.prevent="loadMore($el, '#feed-list')">আরও দেখো</a>
        @endif
    </div>

    <aside class="hidden lg:block">
        <div class="sticky top-20 space-y-4">
            @include('community.partials.quiz-promo')
            <div class="rounded-3xl border border-line bg-card p-5">
                <h2 class="font-bold">এখানে কীভাবে চলে</h2>
                <ul class="mt-3 space-y-2.5 text-sm text-ink-2">
                    <li>✍️ যা জানতে চাও, জিজ্ঞেস করো। কোনো লগইন লাগে না।</li>
                    <li>🙏 যে উত্তর কাজে লাগে, সেটায় “কাজে লেগেছে” দাও। লাইক না, কাজের কথাটাই ওপরে ওঠে।</li>
                    <li>✓ প্রশ্নকারী যে উত্তরে সমাধান পান, সেটা চিহ্নিত করেন।</li>
                    <li>🚩 খারাপ কিছু দেখলে রিপোর্ট করো, আমরা দেখি।</li>
                </ul>
            </div>
        </div>
    </aside>
</div>
@endsection
