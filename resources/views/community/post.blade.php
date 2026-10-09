@extends('community.layout', [
    'active' => 'feed',
    'pageTitle' => __(':title — আমার বাংলাদেশ', ['title' => $post->title]),
    'ogTitle' => $post->title,
    'ogDescription' => $post->body
        ? \App\Community\Text::excerpt($post->body, 180)
        : ($post->answers_count ? \App\Support\Lang::choice(':nটি উত্তর · আমার বাংলাদেশে দেখুন', $post->answers_count) : __('জানা থাকলে উত্তর দিন · আমার বাংলাদেশ')),
    'canonical' => url($post->url()),
])

@section('main')
@php
    $t = $post->typeInfo();
    $countKey = $post->isQuestion() ? ':nটি উত্তর' : ':nটি মন্তব্য';
@endphp
<div class="mx-auto max-w-2xl px-4 pt-4" x-init="postId = {{ $post->id }}; accepted = {{ $post->accepted_answer_id ?? 'null' }}; answersCount = {{ $post->answers_count }}">
    <a href="{{ lroute('feed', [], false) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-2 hover:text-ink">{{ __('‹ সব আলোচনা') }}</a>

    <article class="mt-3" data-own="post:{{ $post->id }}" x-data="{ editing: false }">
        @include('community.partials.tags', ['post' => $post])
        <template data-raw-title>{{ $post->title }}</template><template data-raw>{{ $post->body }}</template>
        <h1 x-show="!editing" id="post-title" class="mt-3 text-[1.7rem] leading-snug font-bold md:text-3xl">{{ $post->title }}</h1>
        <div class="mt-3 flex items-center gap-2 text-sm">
            @include('community.partials.author', ['item' => $post])
            <span class="text-ink-2" aria-hidden="true">·</span>
            <time datetime="{{ $post->created_at->toIso8601String() }}" class="text-ink-2">{{ \App\Support\Lang::ago($post->created_at) }}</time>
            <span id="post-edited" @class(['text-xs text-ink-2', 'hidden' => ! $post->edited_at])>{{ __('(সম্পাদিত)') }}</span>
        </div>
        <div x-show="!editing" id="post-body" class="prose-text mt-4 text-lg empty:hidden">@if ($post->body){{ \App\Community\Text::render($post->body) }}@endif</div>

        {{-- The author edits title and details in place --}}
        <template x-if="editing">
            <form class="mt-3 space-y-2" @submit.prevent="savePost({{ $post->id }}, $el)">
                <label for="edit-title" class="text-sm font-semibold">{{ __('আপনার প্রশ্ন') }}</label>
                <textarea id="edit-title" name="title" rows="2" maxlength="200" required class="field !text-xl font-semibold"
                    x-init="$el.value = $el.closest('article').querySelector('template[data-raw-title]').content.textContent; $el.focus()"></textarea>
                <label for="edit-body" class="text-sm font-semibold">{{ __('বিস্তারিত') }}</label>
                <textarea id="edit-body" name="body" rows="5" maxlength="5000" class="field"
                    x-init="$el.value = $el.closest('article').querySelector('template[data-raw]').content.textContent"></textarea>
                <div class="flex items-center gap-2">
                    <button class="btn-primary !min-h-11 !w-auto !px-5 !text-base" :disabled="busy">{{ __('সেভ') }}</button>
                    <button type="button" class="act-quiet" @click="editing = false">{{ __('থাক') }}</button>
                </div>
            </form>
        </template>

        <div class="mt-5 flex flex-wrap items-center gap-2 border-y border-line py-3">
            <button type="button" class="act" :aria-pressed="marked('post:{{ $post->id }}')" x-show="!mine('post', {{ $post->id }})" @click="helpful('post', {{ $post->id }})">
                🙏 {{ $post->isQuestion() ? __('আমারও জানা দরকার') : __('কাজের পোস্ট') }} <span class="tabular-nums" x-text="count('post:{{ $post->id }}', {{ $post->helpful_count }})">{{ $post->helpful_count ? \App\Support\Lang::num($post->helpful_count) : '' }}</span>
            </button>
            <button type="button" class="act" @click="sharePost(@js($post->title), @js(url($post->url())))">{{ __('↗ শেয়ার') }}</button>
            <span class="ml-auto flex items-center gap-1">
                <button type="button" class="act-quiet" x-show="mine('post', {{ $post->id }})" x-cloak @click="editing = true">{{ __('সম্পাদনা') }}</button>
                <button type="button" class="act-quiet" x-show="mine('post', {{ $post->id }})" x-cloak @click="sheet = { kind: 'delete', type: 'post', id: {{ $post->id }} }">{{ __('মুছে ফেলুন') }}</button>
                <button type="button" class="act-quiet" x-show="!mine('post', {{ $post->id }})" @click="sheet = { kind: 'report', type: 'post', id: {{ $post->id }} }">{{ __('রিপোর্ট') }}</button>
            </span>
        </div>
    </article>

    <section class="mt-6" aria-labelledby="answers-title">
        <h2 id="answers-title" class="text-lg font-bold">
            <span x-text="answersCount ? t(@js($countKey), { n: bn(answersCount), count: answersCount }) : @js($post->isQuestion() ? __('উত্তর') : __('মন্তব্য'))">{{ $post->answers_count ? \App\Support\Lang::choice($countKey, $post->answers_count) : ($post->isQuestion() ? __('উত্তর') : __('মন্তব্য')) }}</span>
        </h2>

        {{-- Threads: each answer with its first replies; long discussions page (endless with JavaScript) --}}
        <div id="answers" class="mt-3 space-y-4">
            @foreach ($answers as $answer)
                @include('community.partials.thread', ['answer' => $answer, 'post' => $post, 'replies' => $replies[$answer->id] ?? collect()])
            @endforeach
        </div>
        @include('community.partials.more', ['list' => '#answers', 'href' => $answers->nextPageUrl()])

        <div x-show="!answersCount" @if ($post->answers_count) x-cloak @endif class="rounded-3xl border border-dashed border-line px-5 py-6 text-center">
            <p class="text-3xl" aria-hidden="true">🤝</p>
            <p class="mt-2 font-bold">{{ $post->isQuestion() ? __('এখনো কেউ উত্তর দেয়নি') : __('এখনো কেউ কিছু বলেনি') }}</p>
            <p class="mt-1 text-sm text-ink-2">{{ __('জানা থাকলে নিচে লিখুন। না জানলে যে জানতে পারেন, তাঁকে পাঠিয়ে দিন।') }}</p>
            <div class="mt-4 flex justify-center gap-4">
                <button type="button" class="share-btn" @click="shareTo('whatsapp', @js($post->title), @js(url($post->url())))"><span class="bg-[#25D366]">@include('partials.icon', ['name' => 'whatsapp'])</span><span>WhatsApp</span></button>
                <button type="button" class="share-btn" @click="shareTo('messenger', @js($post->title), @js(url($post->url())))"><span class="bg-[#0084FF]">@include('partials.icon', ['name' => 'messenger'])</span><span>Messenger</span></button>
                <button type="button" class="share-btn" @click="shareTo('copy', @js($post->title), @js(url($post->url())))"><span class="bg-ink !text-paper">@include('partials.icon', ['name' => 'link'])</span><span>{{ __('লিংক কপি') }}</span></button>
            </div>
        </div>

        {{-- Answer composer --}}
        <form class="composer mt-5" data-draft @submit.prevent="submitAnswer({{ $post->id }}, $el)" x-data="{ body: '', anon: false }">
            <label for="answer-body" class="font-bold">{{ $post->isQuestion() ? __('✍️ আপনার উত্তর') : __('✍️ আপনার মত') }}</label>
            <textarea id="answer-body" name="body" x-model="body" rows="4" maxlength="5000" required class="field mt-2"
                placeholder="{{ $post->isQuestion() ? __('নিজের অভিজ্ঞতা বা জানা তথ্য থেকে লিখুন…') : __('আপনার অভিজ্ঞতা বা মতামত লিখুন…') }}"></textarea>
            <template x-if="signedIn && !myName">
                <div class="mt-2">
                    <label for="answer-name" class="text-sm font-semibold">{{ __('আপনার নাম') }} <span class="font-medium text-ink-2">{{ __('(সবাই দেখবে)') }}</span></label>
                    <input id="answer-name" name="name" maxlength="20" autocomplete="given-name" required class="field mt-1" placeholder="{{ __('যেমন: রাশেদ') }}">
                </div>
            </template>
            @include('community.partials.anon-toggle', ['class' => 'mt-3', 'label' => __('বেনামী হিসেবে উত্তর দিন')])
            <p class="mt-2 text-xs text-ink-2">{{ __('সম্মান রেখে লিখুন · কারও ফোন নম্বর বা ঠিকানা প্রকাশ্যে দেবেন না') }}</p>
            <p x-show="formError" x-cloak class="mt-2 text-sm font-semibold text-danger" x-text="formError"></p>
            <button type="submit" class="btn-primary mt-3" :disabled="busy || body.trim().length < 2" x-text="busy ? t('পাঠাচ্ছি…') : @js($post->isQuestion() ? __('উত্তর দিন') : __('পোস্ট করুন'))">{{ $post->isQuestion() ? __('উত্তর দিন') : __('পোস্ট করুন') }}</button>
            <noscript><p class="mt-2 text-sm text-ink-2">{{ __('উত্তর দিতে ব্রাউজারে JavaScript চালু রাখুন।') }}</p></noscript>
        </form>
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold">{{ __('আরও আলোচনা') }}</h2>
            <div class="mt-3 space-y-3">
                @foreach ($related as $item)
                    @include('community.partials.post-card', ['post' => $item, 'compact' => true])
                @endforeach
            </div>
        </section>
    @endif

    @include('community.partials.quiz-promo', ['class' => 'mt-8'])
</div>
@endsection
