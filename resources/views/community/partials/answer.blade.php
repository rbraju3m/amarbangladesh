{{-- One answer. Owner-only controls are decided in the browser (pages stay cacheable). --}}
<article id="answer-{{ $answer->id }}" class="answer-card" :class="accepted === {{ $answer->id }} && 'is-accepted'">
    <p x-show="accepted === {{ $answer->id }}" @if ($post->accepted_answer_id !== $answer->id) x-cloak @endif class="mb-2 inline-flex items-center gap-1 rounded-full bg-flag-green px-2.5 py-0.5 text-xs font-bold text-white">✓ প্রশ্নকারীর কাজে লেগেছে</p>
    <div class="flex items-center gap-2 text-sm">
        <a href="{{ route('members.show', $answer->member, false) }}" class="flex min-w-0 items-center gap-2 font-semibold hover:text-green-text">
            @include('community.partials.avatar', ['member' => $answer->member])
            <span class="truncate">{{ $answer->member->displayName() }}</span>
        </a>
        <span class="text-ink-2" aria-hidden="true">·</span>
        <time datetime="{{ $answer->created_at->toIso8601String() }}" class="shrink-0 text-ink-2">{{ \App\Support\Bangla::ago($answer->created_at) }}</time>
    </div>
    <div class="prose-text mt-2">{{ \App\Community\Text::render($answer->body) }}</div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" class="act" :aria-pressed="marked('answer:{{ $answer->id }}')" x-show="me !== '{{ $answer->member->code }}'"
            @click="helpful('answer', {{ $answer->id }})">🙏 কাজে লেগেছে <span class="tabular-nums" x-text="count('answer:{{ $answer->id }}', {{ $answer->helpful_count }})">{{ $answer->helpful_count ? \App\Support\Bangla::digits($answer->helpful_count) : '' }}</span></button>
        <span x-show="me === '{{ $answer->member->code }}'" x-cloak class="text-sm text-ink-2">🙏 <span x-text="count('answer:{{ $answer->id }}', {{ $answer->helpful_count }}) || '০'"></span> জনের কাজে লেগেছে</span>
        <button type="button" class="act" x-show="me === '{{ $post->member->code }}' && me !== '{{ $answer->member->code }}'" x-cloak
            :aria-pressed="accepted === {{ $answer->id }}" @click="accept({{ $answer->id }})"
            x-text="accepted === {{ $answer->id }} ? '✓ সমাধান হিসেবে বাছা' : '✓ এটাই সমাধান'"></button>
        <span class="ml-auto flex items-center gap-1">
            <button type="button" class="act-quiet" x-show="me === '{{ $answer->member->code }}'" x-cloak @click="sheet = { kind: 'delete', type: 'answer', id: {{ $answer->id }} }">মুছে ফেলো</button>
            <button type="button" class="act-quiet" x-show="me !== '{{ $answer->member->code }}'" @click="sheet = { kind: 'report', type: 'answer', id: {{ $answer->id }} }">রিপোর্ট</button>
        </span>
    </div>
</article>
