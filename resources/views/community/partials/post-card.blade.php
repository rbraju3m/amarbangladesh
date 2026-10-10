{{-- A post in a list. The title link is stretched over the whole card; tags and the author link sit above it. --}}
@php($compact = $compact ?? false)
@php($photo = $post->photos->first())
<article class="post-card group" data-item="post:{{ $post->id }}">
    @include('community.partials.tags', ['post' => $post])
    <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
            <h3 @class(['mt-2 font-bold leading-snug', 'text-lg' => ! $compact, 'text-base' => $compact])>
                <a href="{{ $post->url() }}" class="after:absolute after:inset-0 after:rounded-3xl group-hover:text-green-text">{{ $post->title }}@if ($photo)<span class="sr-only"> · {{ \App\Support\Lang::choice(':nটি ছবি', $post->photos->count()) }}</span>@endif</a>
            </h3>
            @if ($post->body && ! $compact)
                <p class="mt-1 line-clamp-2 text-ink-2">{{ \App\Community\Text::excerpt($post->body) }}</p>
            @endif
        </div>
        @if ($photo)
            <div @class(['card-thumb', 'size-20' => ! $compact, 'size-14' => $compact]) aria-hidden="true">
                <img src="{{ $photo->url('thumb') }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                @if ($post->photos->count() > 1)<span class="card-thumb-more">+{{ \App\Support\Lang::num($post->photos->count() - 1) }}</span>@endif
            </div>
        @endif
    </div>
    {{-- Wraps on narrow cards (the badges drop to their own line) so the author's name never shrinks to a letter. --}}
    <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm text-ink-2">
        @include('community.partials.author', ['item' => $post, 'linkClass' => 'relative z-10 !min-w-[5.5rem] !font-semibold text-ink-2 hover:text-ink', 'avatarClass' => 'size-6 text-xs'])
        <span aria-hidden="true">·</span>
        <time datetime="{{ $post->created_at->toIso8601String() }}" class="shrink-0">{{ \App\Support\Lang::ago($post->created_at) }}</time>
        <span class="ml-auto flex shrink-0 items-center gap-2">
            @if ($post->helpful_count)
                <span title="{{ __('কাজের মনে করেছে') }}">🙏 {{ \App\Support\Lang::num($post->helpful_count) }}</span>
            @endif
            @if ($post->accepted_answer_id)
                <span class="pill bg-flag-green/10 text-green-text">{{ __('✓ সমাধান হয়েছে') }}</span>
            @elseif ($post->answers_count)
                <span class="pill bg-paper-2 text-ink">💬 {{ \App\Support\Lang::choice($post->isQuestion() ? ':nটি উত্তর' : ':nটি মন্তব্য', $post->answers_count) }}</span>
            @elseif ($post->isQuestion())
                <span class="pill bg-warn/10 text-warn">{{ __('উত্তর দরকার') }}</span>
            @endif
        </span>
    </div>
</article>
