{{-- One answer. Owner-only controls are decided in the browser (pages stay cacheable). --}}
<article id="answer-{{ $answer->id }}" data-own="answer:{{ $answer->id }}" class="answer-card" :class="accepted === {{ $answer->id }} && 'is-accepted'" x-data="{ editing: false }">
    <p x-show="accepted === {{ $answer->id }}" @if ($post->accepted_answer_id !== $answer->id) x-cloak @endif class="mb-2 inline-flex items-center gap-1 rounded-full bg-flag-green px-2.5 py-0.5 text-xs font-bold text-white">{{ __('✓ প্রশ্নকারীর কাজে লেগেছে') }}</p>
    <div class="flex items-center gap-2 text-sm">
        @include('community.partials.author', ['item' => $answer])
        <span class="text-ink-2" aria-hidden="true">·</span>
        <time datetime="{{ $answer->created_at->toIso8601String() }}" class="shrink-0 text-ink-2">{{ \App\Support\Lang::ago($answer->created_at) }}</time>
        @if ($answer->edited_at)<span class="shrink-0 text-xs text-ink-2">{{ __('(সম্পাদিত)') }}</span>@endif
    </div>
    <template data-raw>{{ $answer->body }}</template>@if ($answer->body_html)<template data-raw-html>{{ $answer->body_html }}</template>@endif
    @if ($answer->photos->isNotEmpty())<template data-raw-photos>@json($answer->photos->map->present())</template>@endif
    <div x-show="!editing" class="prose-text mt-2">{{ \App\Community\RichText::render($answer) }}</div>
    @include('community.partials.edit-form', ['id' => $answer->id, 'type' => 'answer', 'rich' => true, 'photos' => ! $answer->is_anonymous])
    <div x-show="!editing" class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" class="act" :aria-pressed="marked('answer:{{ $answer->id }}')" x-show="!mine('answer', {{ $answer->id }})"
            @click="helpful('answer', {{ $answer->id }})">{{ __('🙏 কাজে লেগেছে') }} <span class="tabular-nums" x-text="count('answer:{{ $answer->id }}', {{ $answer->helpful_count }})">{{ $answer->helpful_count ? \App\Support\Lang::num($answer->helpful_count) : '' }}</span></button>
        <span x-show="mine('answer', {{ $answer->id }})" x-cloak class="text-sm text-ink-2">🙏 <span x-text="t(':n জনের কাজে লেগেছে', { n: count('answer:{{ $answer->id }}', {{ $answer->helpful_count }}) || bn(0), count: counts['answer:{{ $answer->id }}'] ?? {{ $answer->helpful_count }} })"></span></span>
        <button type="button" class="act" x-show="mine('post', {{ $post->id }}) && !mine('answer', {{ $answer->id }})" x-cloak
            :aria-pressed="accepted === {{ $answer->id }}" @click="accept({{ $answer->id }})"
            x-text="accepted === {{ $answer->id }} ? t('✓ সমাধান হিসেবে বাছা') : t('✓ এটাই সমাধান')"></button>
        <button type="button" class="act-quiet" @click="reply({{ $answer->id }}, @js($answer->publicName()))">{{ __('↩ জবাব দিন') }}</button>
        <span class="ml-auto flex items-center gap-1">
            <button type="button" class="act-quiet" x-show="mine('answer', {{ $answer->id }})" x-cloak @click="editing = true">{{ __('সম্পাদনা') }}</button>
            <button type="button" class="act-quiet" x-show="mine('answer', {{ $answer->id }})" x-cloak @click="sheet = { kind: 'delete', type: 'answer', id: {{ $answer->id }} }">{{ __('মুছে ফেলুন') }}</button>
            <button type="button" class="act-quiet" x-show="!mine('answer', {{ $answer->id }})" @click="sheet = { kind: 'report', type: 'answer', id: {{ $answer->id }} }">{{ __('রিপোর্ট') }}</button>
        </span>
    </div>
</article>
