{{-- A post in a list. The title link is stretched over the whole card; tags and the author link sit above it. --}}
@php($compact = $compact ?? false)
<article class="post-card group">
    @include('community.partials.tags', ['post' => $post])
    <h3 @class(['mt-2 font-bold leading-snug', 'text-lg' => ! $compact, 'text-base' => $compact])>
        <a href="{{ $post->url() }}" class="after:absolute after:inset-0 after:rounded-3xl group-hover:text-green-text">{{ $post->title }}</a>
    </h3>
    @if ($post->body && ! $compact)
        <p class="mt-1 line-clamp-2 text-ink-2">{{ \App\Community\Text::excerpt($post->body) }}</p>
    @endif
    <div class="mt-3 flex items-center gap-2 text-sm text-ink-2">
        <a href="{{ route('members.show', $post->member, false) }}" class="relative z-10 flex min-w-0 items-center gap-2 hover:text-ink">
            @include('community.partials.avatar', ['member' => $post->member, 'class' => 'size-6 text-xs'])
            <span class="truncate font-semibold">{{ $post->member->displayName() }}</span>
        </a>
        <span aria-hidden="true">·</span>
        <time datetime="{{ $post->created_at->toIso8601String() }}" class="shrink-0">{{ \App\Support\Bangla::ago($post->created_at) }}</time>
        <span class="ml-auto flex shrink-0 items-center gap-2">
            @if ($post->helpful_count)
                <span title="কাজের মনে করেছে">🙏 {{ \App\Support\Bangla::digits($post->helpful_count) }}</span>
            @endif
            @if ($post->accepted_answer_id)
                <span class="pill bg-flag-green/10 text-green-text">✓ সমাধান হয়েছে</span>
            @elseif ($post->answers_count)
                <span class="pill bg-paper-2 text-ink">💬 {{ \App\Support\Bangla::digits($post->answers_count) }}টি {{ $post->isQuestion() ? 'উত্তর' : 'মন্তব্য' }}</span>
            @elseif ($post->isQuestion())
                <span class="pill bg-flag-red/10 text-flag-red">উত্তর দরকার</span>
            @endif
        </span>
    </div>
</article>
