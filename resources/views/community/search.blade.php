@extends('community.layout', [
    'active' => 'search',
    'pageTitle' => $query !== '' ? __(':q — খোঁজ · আমার বাংলাদেশ', ['q' => $query]) : __('খুঁজুন · আমার বাংলাদেশ'),
    'noindex' => true,
])

@section('main')
<div class="mx-auto max-w-2xl px-4 pt-5" @if ($posts) x-init="track('search_performed', { meta: { results: {{ $posts->total() }}, len: {{ mb_strlen($query) }} } })" @endif>
    <form role="search" action="{{ lroute('search', [], false) }}" method="get" class="flex gap-2">
        <label for="search-q" class="sr-only">{{ __('কী খুঁজছেন?') }}</label>
        <input id="search-q" type="search" name="q" value="{{ $query }}" maxlength="100" enterkeyhint="search" @if ($query === '') autofocus @endif
            class="field min-w-0 flex-1 !text-lg" placeholder="{{ __('যেমন: পাসপোর্ট, ভর্তি, ডেন্টিস্ট') }}">
        <button class="btn-primary !min-h-12 !w-auto !px-5 !text-base">{{ __('খুঁজুন') }}</button>
    </form>

    @if ($posts === null)
        <p class="mt-6 text-ink-2">{{ __('প্রশ্ন বা আলোচনার কোনো শব্দ লিখুন। যত নির্দিষ্ট লিখবেন, তত ভালো মিলবে।') }}</p>
    @elseif ($posts->total() === 0)
        <div class="mt-6 rounded-3xl border border-dashed border-line px-6 py-10 text-center">
            <p class="text-4xl" aria-hidden="true">🔎</p>
            <p class="mt-3 text-lg font-bold">{{ __('এ নিয়ে এখনো কেউ কিছু লেখেননি') }}</p>
            <p class="mx-auto mt-1 max-w-sm text-ink-2">{{ __('প্রশ্নটা আপনিই করুন, যাঁরা জানেন তাঁরা উত্তর দেবেন।') }}</p>
            <a href="{{ lroute('ask', ['title' => $query], false) }}" class="btn-primary mt-5 !w-auto">{{ __('✍️ এটাই জিজ্ঞেস করি') }}</a>
        </div>
    @else
        <p class="mt-5 text-sm text-ink-2">{{ \App\Support\Lang::choice(':nটি ফলাফল', $posts->total()) }}</p>
        <div id="search-list" class="mt-3 space-y-3">
            @include('community.partials.post-list', ['posts' => $posts])
        </div>
        @include('community.partials.more', ['list' => '#search-list', 'href' => $posts->nextPageUrl()])
        <p class="mt-8 text-center text-sm text-ink-2">{{ __('যা খুঁজছেন পেলেন না?') }} <a href="{{ lroute('ask', ['title' => $query], false) }}" class="font-semibold text-green-text underline">{{ __('জিজ্ঞেস করুন') }}</a></p>
    @endif
</div>
@endsection
