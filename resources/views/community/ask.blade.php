@extends('community.layout', [
    'active' => 'ask',
    'pageTitle' => 'জিজ্ঞেস করো · আমার বাংলাদেশ',
    'ogTitle' => 'কী জানতে চাও? বাংলাদেশের মানুষকে জিজ্ঞেস করো',
])

@php
    $examples = [
        'ঢাকায় ভালো আর সাশ্রয়ী ডেন্টিস্ট কোথায় পাবো?',
        'HSC Physics পড়ার জন্য কোন বই বা ভিডিও সবচেয়ে ভালো?',
        'বিদেশে যাওয়ার আগে কী কী প্রস্তুতি নেওয়া দরকার?',
        'আমাদের এলাকায় ভালো ল্যাপটপ সার্ভিসিং কোথায় হয়?',
        'পাসপোর্ট রিনিউ করতে কী কী কাগজ লাগে?',
    ];
@endphp

@section('main')
<div class="mx-auto max-w-2xl px-4 pt-5">
    <h1 class="text-[1.9rem] leading-tight font-bold md:text-4xl">কী জানতে চাও?</h1>
    <p class="mt-1.5 text-ink-2">বাংলাদেশের মানুষ উত্তর দেবে। যত পরিষ্কার করে লিখবে, তত ভালো উত্তর পাবে।</p>

    <form class="mt-5" @submit.prevent="submitPost($el)"
        x-data="{ type: @js($prefill['type']), title: '', details: false, category: @js($prefill['category']), ex: 0, examples: @js($examples) }"
        x-init="setInterval(() => title || (ex = (ex + 1) % examples.length), 3500)">

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]" role="radiogroup" aria-label="পোস্টের ধরন">
            @foreach ($types as $key => $t)
                <button type="button" role="radio" class="filter-chip" :class="type === '{{ $key }}' && 'is-on'" :aria-checked="type === '{{ $key }}'" @click="type = '{{ $key }}'">{{ $t['emoji'] }} {{ $t['label'] }}</button>
            @endforeach
            <input type="hidden" name="type" :value="type">
        </div>

        <label for="ask-title" class="sr-only">তোমার প্রশ্ন</label>
        <textarea id="ask-title" name="title" x-model="title" rows="3" maxlength="200" required autofocus
            class="field mt-3 !text-xl !leading-snug font-semibold" :placeholder="examples[ex]"></textarea>
        <div class="mt-1 flex justify-between text-xs text-ink-2">
            <span x-text="type === 'question' || type === 'help' ? 'এক লাইনে মূল প্রশ্নটা লেখো' : 'এক লাইনে মূল কথাটা লেখো'"></span>
            <span class="tabular-nums" x-text="`${bn(title.length)}/২০০`"></span>
        </div>

        <button type="button" x-show="!details" @click="details = true; $nextTick(() => $refs.body.focus())" class="mt-3 text-sm font-semibold text-green-text">＋ আরও বিস্তারিত লেখো <span class="font-medium text-ink-2">(ঐচ্ছিক)</span></button>
        <div x-show="details" x-cloak class="mt-3">
            <label for="ask-body" class="text-sm font-semibold">বিস্তারিত</label>
            <textarea id="ask-body" name="body" x-ref="body" rows="5" maxlength="5000" class="field mt-1" placeholder="কী চেষ্টা করেছো, কোথায় আটকে আছো, বাজেট কত — যা জানালে উত্তর দিতে সুবিধা হয়"></textarea>
        </div>

        <fieldset class="mt-5">
            <legend class="text-sm font-semibold">বিষয় <span class="font-medium text-ink-2">(ঐচ্ছিক)</span></legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($categories as $c)
                    <button type="button" class="filter-chip" :class="category === '{{ $c['slug'] }}' && 'is-on'" :aria-pressed="category === '{{ $c['slug'] }}'"
                        @click="category = category === '{{ $c['slug'] }}' ? '' : '{{ $c['slug'] }}'">{{ $c['emoji'] }} {{ $c['name'] }}</button>
                @endforeach
            </div>
            <input type="hidden" name="category" :value="category">
        </fieldset>

        <div class="mt-5">
            <label for="ask-area" class="text-sm font-semibold">📍 কোন এলাকা নিয়ে? <span class="font-medium text-ink-2">(ঐচ্ছিক)</span></label>
            <select id="ask-area" name="area" class="field mt-1">
                <option value="">সারা বাংলাদেশ / নির্দিষ্ট এলাকা না</option>
                @foreach ($districts as $division => $list)
                    <optgroup label="{{ $division }} বিভাগ">
                        @foreach ($list as $a)
                            <option value="{{ $a['slug'] }}" @selected($prefill['area'] === $a['slug'])>{{ $a['name'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <template x-if="!myName">
            <div class="mt-5">
                <label for="ask-name" class="text-sm font-semibold">তোমার নাম <span class="font-medium text-ink-2">(সবাই দেখবে)</span></label>
                <input id="ask-name" name="name" maxlength="20" autocomplete="given-name" required class="field mt-1" placeholder="যেমন: রাশেদ">
            </div>
        </template>
        <p x-show="myName" x-cloak class="mt-5 text-sm text-ink-2">পোস্ট হবে <span class="font-semibold text-ink" x-text="myName"></span> নামে</p>

        <p x-show="formError" x-cloak class="mt-3 text-sm font-semibold text-flag-red" x-text="formError"></p>
        <button type="submit" class="btn-primary mt-5" :disabled="busy || title.trim().length < 8"
            x-text="busy ? 'পোস্ট হচ্ছে…' : (type === 'question' || type === 'help' ? 'প্রশ্নটা পোস্ট করো' : 'পোস্ট করো')">প্রশ্নটা পোস্ট করো</button>
        <p class="mt-3 text-center text-xs leading-relaxed text-ink-2">কোনো লগইন লাগে না · সম্মান রেখে লেখো · কারও ফোন নম্বর বা ঠিকানা প্রকাশ্যে দিও না</p>
        <noscript><p class="mt-2 text-center text-sm text-flag-red">পোস্ট করতে ব্রাউজারে JavaScript চালু রাখো।</p></noscript>
    </form>
</div>
@endsection
