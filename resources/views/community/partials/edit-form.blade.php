{{--
    Inline editing of an answer or reply by its author. The text comes from the item's
    <template data-raw> (exactly what was written), so links and line breaks survive the round trip.
    Answers (`rich`) open the formatting editor on <template data-raw-html>; replies stay plain.
--}}
<template x-if="editing">
    <form class="mt-2" @submit.prevent="saveAnswer({{ $id }}, $el)">
        <label class="sr-only" for="edit-{{ $type }}-{{ $id }}">{{ __('সম্পাদনা') }}</label>
        @if ($rich ?? false)
            <input type="hidden" name="format" value="">
            <textarea id="edit-{{ $type }}-{{ $id }}" name="body" rows="4" maxlength="20000" required class="field" x-rich.now
                x-init="editText($el); $el.focus()"></textarea>
        @else
            <textarea id="edit-{{ $type }}-{{ $id }}" name="body" rows="4" maxlength="5000" required class="field"
                x-init="editText($el); $el.focus()"></textarea>
        @endif
        <div class="mt-2 flex items-center gap-2">
            <button class="btn-primary !min-h-11 !w-auto !px-5 !text-base" :disabled="busy">{{ __('সেভ') }}</button>
            <button type="button" class="act-quiet" @click="editing = false">{{ __('থাক') }}</button>
        </div>
    </form>
</template>
