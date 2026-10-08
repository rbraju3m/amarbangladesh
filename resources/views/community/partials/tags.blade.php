{{-- Type, category and area of a post. The links sit above a card's stretched link. --}}
@php($t = $post->typeInfo())
<div class="relative z-10 flex flex-wrap items-center gap-1.5">
    <span class="tag tag-{{ $post->type }}">{{ $t['emoji'] }} {{ \App\Models\Post::typeLabel($post->type) }}</span>
    @if ($post->category)
        <a href="{{ lroute('feed', ['category' => $post->category->slug], false) }}" class="tag hover:border-ink-2">{{ $post->category->emoji }} {{ $post->category->name() }}</a>
    @endif
    @if ($post->area)
        <a href="{{ lroute('feed', ['area' => $post->area->slug], false) }}" class="tag hover:border-ink-2">📍 {{ $post->area->name() }}</a>
    @endif
</div>
