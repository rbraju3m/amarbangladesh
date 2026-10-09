{{--
    A top-level answer with its replies and a reply box. The thread keeps who is being replied to,
    and how many replies are still hidden behind "show more".
    $replies: the replies already loaded (the first few, or all with ?thread=ID).
--}}
@php($replies = $replies ?? collect())
<div id="thread-{{ $answer->id }}" data-item="answer:{{ $answer->id }}" class="thread"
    x-data="{
        replyTo: null, more: {{ max(0, $answer->replies_count - $replies->count()) }}, after: {{ $replies->last()?->id ?? 0 }}, anon: false,
        reply(id, name) { this.replyTo = { id, name }; this.$nextTick(() => this.$refs.replyBody?.focus()); },
    }">
    @if ($answer->isPublished())
        @include('community.partials.answer', ['answer' => $answer, 'post' => $post])
    @else
        <p class="answer-card text-sm text-ink-2">{{ __('এই উত্তরটা আর নেই।') }}</p>
    @endif

    <div id="replies-{{ $answer->id }}" class="replies" x-show="$el.children.length || replyTo">
        @foreach ($replies as $reply)
            @include('community.partials.reply', ['reply' => $reply, 'post' => $post])
        @endforeach
    </div>
    <button type="button" x-show="more > 0" @if ($answer->replies_count <= $replies->count()) x-cloak @endif class="act-quiet ml-4 md:ml-6"
        @click="loadReplies({{ $answer->id }}, $data)" x-text="t(':nটি আরও জবাব দেখুন', { n: bn(more), count: more })"></button>

    {{-- Reply box: opens from any "reply" in this thread; the reply keeps who it answers. --}}
    <template x-if="replyTo">
        <form class="reply-composer" @submit.prevent="submitReply({{ $post->id }}, $el, $data)">
            <p class="text-xs font-semibold text-ink-2" x-text="t('↪ :name-কে জবাব দিচ্ছেন', { name: replyTo.name })"></p>
            <input type="hidden" name="parent" :value="replyTo.id">
            <label class="sr-only" :for="`reply-body-{{ $answer->id }}`">{{ __('আপনার জবাব') }}</label>
            <textarea :id="`reply-body-{{ $answer->id }}`" name="body" x-ref="replyBody" rows="2" maxlength="5000" required class="field mt-1.5" placeholder="{{ __('আপনার জবাব লিখুন…') }}"></textarea>
            <template x-if="signedIn && !myName">
                <input name="name" maxlength="20" autocomplete="given-name" required class="field mt-2" placeholder="{{ __('আপনার নাম') }}" aria-label="{{ __('আপনার নাম') }}">
            </template>
            @include('community.partials.anon-toggle', ['class' => 'mt-2', 'label' => __('বেনামী হিসেবে জবাব দিন')])
            <div class="mt-2 flex items-center gap-2">
                <button class="btn-primary !min-h-11 !w-auto !px-5 !text-base" :disabled="busy">{{ __('জবাব পাঠান') }}</button>
                <button type="button" class="act-quiet" @click="replyTo = null">{{ __('থাক') }}</button>
            </div>
        </form>
    </template>
</div>
