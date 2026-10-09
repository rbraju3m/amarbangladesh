{{--
    One reply in a thread (smaller than an answer). A reply to another reply says whose words it answers;
    the indentation stays at one level so threads stay readable on a phone. Owner controls are decided
    in the browser, like answers.
--}}
<article id="answer-{{ $reply->id }}" data-own="answer:{{ $reply->id }}" data-item="answer:{{ $reply->id }}" class="reply-card" x-data="{ editing: false }">
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        @include('community.partials.author', ['item' => $reply, 'avatarClass' => 'size-6 text-xs'])
        <span class="text-ink-2" aria-hidden="true">·</span>
        <time datetime="{{ $reply->created_at->toIso8601String() }}" class="text-ink-2">{{ \App\Support\Lang::ago($reply->created_at) }}</time>
        @if ($reply->edited_at)<span class="text-xs text-ink-2">{{ __('(সম্পাদিত)') }}</span>@endif
    </div>
    @if ($reply->parent_id !== $reply->thread_id && $reply->parent)
        <a href="#answer-{{ $reply->parent_id }}" class="mt-1 inline-block text-xs font-semibold text-ink-2 hover:text-ink">{{ __('↪ :name-কে', ['name' => $reply->parent->publicName()]) }}</a>
    @endif
    <template data-raw>{{ $reply->body }}</template>
    <div x-show="!editing" class="prose-text mt-1.5">{{ \App\Community\Text::render($reply->body) }}</div>
    @include('community.partials.edit-form', ['id' => $reply->id, 'type' => 'answer'])
    <div x-show="!editing" class="mt-1.5 flex flex-wrap items-center gap-1">
        <button type="button" class="act-quiet !min-h-9 !px-2" :aria-pressed="marked('answer:{{ $reply->id }}')" x-show="!mine('answer', {{ $reply->id }})"
            @click="helpful('answer', {{ $reply->id }})">🙏 <span class="tabular-nums" x-text="count('answer:{{ $reply->id }}', {{ $reply->helpful_count }})">{{ $reply->helpful_count ? \App\Support\Lang::num($reply->helpful_count) : '' }}</span></button>
        <button type="button" class="act-quiet !min-h-9 !px-2" @click="reply({{ $reply->id }}, @js($reply->publicName()))">{{ __('↩ জবাব দিন') }}</button>
        <span class="ml-auto flex items-center gap-1">
            <button type="button" class="act-quiet !min-h-9 !px-2" x-show="mine('answer', {{ $reply->id }})" x-cloak @click="editing = true">{{ __('সম্পাদনা') }}</button>
            <button type="button" class="act-quiet !min-h-9 !px-2" x-show="mine('answer', {{ $reply->id }})" x-cloak @click="sheet = { kind: 'delete', type: 'answer', id: {{ $reply->id }} }">{{ __('মুছে ফেলুন') }}</button>
            <button type="button" class="act-quiet !min-h-9 !px-2" x-show="!mine('answer', {{ $reply->id }})" @click="sheet = { kind: 'report', type: 'answer', id: {{ $reply->id }} }">{{ __('রিপোর্ট') }}</button>
        </span>
    </div>
</article>
