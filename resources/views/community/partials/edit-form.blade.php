{{--
    Inline editing of an answer or reply by its author. The text comes from the item's
    <template data-raw> (exactly what was written), so links and line breaks survive the round trip.
--}}
<template x-if="editing">
    <form class="mt-2" @submit.prevent="saveAnswer({{ $id }}, $el)">
        <label class="sr-only" for="edit-{{ $type }}-{{ $id }}">{{ __('সম্পাদনা') }}</label>
        <textarea id="edit-{{ $type }}-{{ $id }}" name="body" rows="4" maxlength="5000" required class="field"
            x-init="$el.value = $el.closest('article').querySelector('template[data-raw]').content.textContent; $el.focus()"></textarea>
        <div class="mt-2 flex items-center gap-2">
            <button class="btn-primary !min-h-11 !w-auto !px-5 !text-base" :disabled="busy">{{ __('সেভ') }}</button>
            <button type="button" class="act-quiet" @click="editing = false">{{ __('থাক') }}</button>
        </div>
    </form>
</template>
