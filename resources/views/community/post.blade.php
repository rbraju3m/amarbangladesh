@extends('community.layout', [
    'active' => 'feed',
    'pageTitle' => $post->title.' — আমার বাংলাদেশ',
    'ogTitle' => $post->title,
    'ogDescription' => $post->body
        ? \App\Community\Text::excerpt($post->body, 180)
        : ($post->answers_count ? \App\Support\Bangla::digits($post->answers_count).'টি উত্তর · আমার বাংলাদেশে দেখো' : 'জানা থাকলে উত্তর দাও · আমার বাংলাদেশ'),
    'canonical' => url($post->url()),
])

@section('main')
@php($t = $post->typeInfo())
<div class="mx-auto max-w-2xl px-4 pt-4" x-init="postId = {{ $post->id }}; accepted = {{ $post->accepted_answer_id ?? 'null' }}; answersCount = {{ $post->answers_count }}">
    <a href="{{ route('feed', [], false) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-2 hover:text-ink">‹ সব আলোচনা</a>

    <article class="mt-3">
        @include('community.partials.tags', ['post' => $post])
        <h1 class="mt-3 text-[1.7rem] leading-snug font-bold md:text-3xl">{{ $post->title }}</h1>
        <div class="mt-3 flex items-center gap-2 text-sm">
            <a href="{{ route('members.show', $post->member, false) }}" class="flex min-w-0 items-center gap-2 font-semibold hover:text-green-text">
                @include('community.partials.avatar', ['member' => $post->member])
                <span class="truncate">{{ $post->member->displayName() }}</span>
            </a>
            <span class="text-ink-2" aria-hidden="true">·</span>
            <time datetime="{{ $post->created_at->toIso8601String() }}" class="text-ink-2">{{ \App\Support\Bangla::ago($post->created_at) }}</time>
        </div>
        @if ($post->body)
            <div class="prose-text mt-4 text-lg">{{ \App\Community\Text::render($post->body) }}</div>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-2 border-y border-line py-3">
            <button type="button" class="act" :aria-pressed="marked('post:{{ $post->id }}')" x-show="me !== '{{ $post->member->code }}'" @click="helpful('post', {{ $post->id }})">
                🙏 {{ $post->isQuestion() ? 'আমারও জানা দরকার' : 'কাজের পোস্ট' }} <span class="tabular-nums" x-text="count('post:{{ $post->id }}', {{ $post->helpful_count }})">{{ $post->helpful_count ? \App\Support\Bangla::digits($post->helpful_count) : '' }}</span>
            </button>
            <button type="button" class="act" @click="sharePost(@js($post->title), @js(url($post->url())))">↗ শেয়ার</button>
            <span class="ml-auto flex items-center gap-1">
                <button type="button" class="act-quiet" x-show="me === '{{ $post->member->code }}'" x-cloak @click="sheet = { kind: 'delete', type: 'post', id: {{ $post->id }} }">মুছে ফেলো</button>
                <button type="button" class="act-quiet" x-show="me !== '{{ $post->member->code }}'" @click="sheet = { kind: 'report', type: 'post', id: {{ $post->id }} }">রিপোর্ট</button>
            </span>
        </div>
    </article>

    <section class="mt-6" aria-labelledby="answers-title">
        <h2 id="answers-title" class="text-lg font-bold">
            <span x-text="answersCount ? `${bn(answersCount)}টি {{ $post->isQuestion() ? 'উত্তর' : 'মন্তব্য' }}` : '{{ $post->isQuestion() ? 'উত্তর' : 'মন্তব্য' }}'">{{ $post->answers_count ? \App\Support\Bangla::digits($post->answers_count).'টি ' : '' }}{{ $post->isQuestion() ? 'উত্তর' : 'মন্তব্য' }}</span>
        </h2>

        <div id="answers" class="mt-3 space-y-3">
            @foreach ($answers as $answer)
                @include('community.partials.answer', ['answer' => $answer, 'post' => $post])
            @endforeach
        </div>

        <div x-show="!answersCount" @if ($post->answers_count) x-cloak @endif class="rounded-3xl border border-dashed border-line px-5 py-6 text-center">
            <p class="text-3xl" aria-hidden="true">🤝</p>
            <p class="mt-2 font-bold">{{ $post->isQuestion() ? 'এখনো কেউ উত্তর দেয়নি' : 'এখনো কেউ কিছু বলেনি' }}</p>
            <p class="mt-1 text-sm text-ink-2">জানা থাকলে নিচে লেখো। না জানলে যে জানতে পারে, তাকে পাঠিয়ে দাও।</p>
            <div class="mt-4 flex justify-center gap-4">
                <button type="button" class="share-btn" @click="shareTo('whatsapp', @js($post->title), @js(url($post->url())))"><span class="bg-[#25D366]">@include('partials.icon', ['name' => 'whatsapp'])</span><span>WhatsApp</span></button>
                <button type="button" class="share-btn" @click="shareTo('messenger', @js($post->title), @js(url($post->url())))"><span class="bg-[#0084FF]">@include('partials.icon', ['name' => 'messenger'])</span><span>Messenger</span></button>
                <button type="button" class="share-btn" @click="shareTo('copy', @js($post->title), @js(url($post->url())))"><span class="bg-ink !text-paper">@include('partials.icon', ['name' => 'link'])</span><span>লিংক কপি</span></button>
            </div>
        </div>

        {{-- Answer composer --}}
        <form class="composer mt-5" @submit.prevent="submitAnswer({{ $post->id }}, $el)" x-data="{ body: '' }">
            <label for="answer-body" class="font-bold">{{ $post->isQuestion() ? '✍️ তোমার উত্তর' : '✍️ তোমার মত' }}</label>
            <textarea id="answer-body" name="body" x-model="body" rows="4" maxlength="5000" required class="field mt-2"
                placeholder="{{ $post->isQuestion() ? 'নিজের অভিজ্ঞতা বা জানা তথ্য থেকে লেখো…' : 'তোমার অভিজ্ঞতা বা মতামত লেখো…' }}"></textarea>
            <template x-if="!myName">
                <div class="mt-2">
                    <label for="answer-name" class="text-sm font-semibold">তোমার নাম <span class="font-medium text-ink-2">(সবাই দেখবে)</span></label>
                    <input id="answer-name" name="name" maxlength="20" autocomplete="given-name" required class="field mt-1" placeholder="যেমন: রাশেদ">
                </div>
            </template>
            <p class="mt-2 text-xs text-ink-2">সম্মান রেখে লেখো · কারও ফোন নম্বর বা ঠিকানা প্রকাশ্যে দিও না</p>
            <p x-show="formError" x-cloak class="mt-2 text-sm font-semibold text-flag-red" x-text="formError"></p>
            <button type="submit" class="btn-primary mt-3" :disabled="busy || body.trim().length < 2" x-text="busy ? 'পাঠাচ্ছি…' : '{{ $post->isQuestion() ? 'উত্তর দাও' : 'পোস্ট করো' }}'">{{ $post->isQuestion() ? 'উত্তর দাও' : 'পোস্ট করো' }}</button>
            <noscript><p class="mt-2 text-sm text-ink-2">উত্তর দিতে ব্রাউজারে JavaScript চালু রাখো।</p></noscript>
        </form>
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold">আরও আলোচনা</h2>
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
