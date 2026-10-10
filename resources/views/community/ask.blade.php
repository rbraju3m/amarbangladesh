@extends('community.layout', [
    'active' => 'ask',
    'pageTitle' => __('জিজ্ঞেস করুন · আমার বাংলাদেশ'),
    'ogTitle' => __('কী জানতে চান? বাংলাদেশের মানুষকে জিজ্ঞেস করুন'),
])

@php
    $examples = [
        __('ঢাকায় ভালো আর সাশ্রয়ী ডেন্টিস্ট কোথায় পাবো?'),
        __('HSC Physics পড়ার জন্য কোন বই বা ভিডিও সবচেয়ে ভালো?'),
        __('বিদেশে যাওয়ার আগে কী কী প্রস্তুতি নেওয়া দরকার?'),
        __('আমাদের এলাকায় ভালো ল্যাপটপ সার্ভিসিং কোথায় হয়?'),
        __('পাসপোর্ট রিনিউ করতে কী কী কাগজ লাগে?'),
    ];
@endphp

@section('main')
<div class="mx-auto max-w-2xl px-4 pt-5">
    <h1 class="text-[1.9rem] leading-tight font-bold md:text-4xl">{{ __('কী জানতে চান?') }}</h1>
    <p class="mt-1.5 text-ink-2">{{ __('বাংলাদেশের মানুষ উত্তর দেবে। যত পরিষ্কার করে লিখবেন, তত ভালো উত্তর পাবেন।') }}</p>
    <p x-show="!signedIn" x-cloak class="mt-3 rounded-2xl bg-flag-green/10 px-4 py-2.5 text-sm text-green-text">🔐 {{ __('পোস্ট করার সময় লগইন করতে বলা হবে। আগে লিখে ফেলুন, লেখা হারাবে না।') }}</p>

    <form class="mt-5" data-draft @submit.prevent="submitPost($el)"
        x-data="{ type: @js($prefill['type']), title: @js($prefill['title']), details: false, anon: false, photoCount: 0, category: @js($prefill['category']), ex: 0, examples: @js($examples) }"
        x-init="setInterval(() => title || (ex = (ex + 1) % examples.length), 3500)">

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]" role="radiogroup" aria-label="{{ __('পোস্টের ধরন') }}">
            @foreach ($types as $key => $t)
                <button type="button" role="radio" class="filter-chip" :class="type === '{{ $key }}' && 'is-on'" :aria-checked="type === '{{ $key }}'" @click="type = '{{ $key }}'">{{ $t['emoji'] }} {{ \App\Models\Post::typeLabel($key) }}</button>
            @endforeach
            <input type="hidden" name="type" :value="type">
        </div>

        <label for="ask-title" class="sr-only">{{ __('আপনার প্রশ্ন') }}</label>
        <textarea id="ask-title" name="title" x-model="title" rows="3" maxlength="200" required autofocus
            class="field mt-3 !text-xl !leading-snug font-semibold" :placeholder="examples[ex]"></textarea>
        <div class="mt-1 flex justify-between text-xs text-ink-2">
            <span x-text="type === 'question' || type === 'help' ? t('এক লাইনে মূল প্রশ্নটা লিখুন') : t('এক লাইনে মূল কথাটা লিখুন')"></span>
            <span class="tabular-nums" x-text="`${bn(title.length)}/${bn(200)}`"></span>
        </div>

        {{-- Has this been asked? Close titles appear while typing (they open in a new tab, the draft stays). --}}
        <div x-data="similarQuestions" x-effect="lookup(title)" x-show="count" x-cloak class="mt-3 rounded-2xl border border-warn/30 bg-warn/5 p-3" aria-live="polite">
            <p class="text-sm font-bold">{{ __('এমন প্রশ্ন আগে হয়েছে কি না দেখে নিন') }}</p>
            <div x-html="html" @click="$event.target.closest('a') && track('similar_clicked')"></div>
        </div>

        <button type="button" x-show="!details" @click="details = true; $nextTick(() => ($refs.body._rich ?? $refs.body).focus())" class="mt-3 text-sm font-semibold text-green-text">{{ __('＋ আরও বিস্তারিত লিখুন') }} <span class="font-medium text-ink-2">{{ __('(ঐচ্ছিক)') }}</span></button>
        <div x-show="details" x-cloak class="mt-3">
            <label for="ask-body" class="text-sm font-semibold">{{ __('বিস্তারিত') }}</label>
            <input type="hidden" name="format" value="">
            <textarea id="ask-body" name="body" x-ref="body" x-rich.eager="'post'" rows="5" maxlength="20000" class="field mt-1" placeholder="{{ __('কী চেষ্টা করেছেন, কোথায় আটকে আছেন, বাজেট কত — যা জানালে উত্তর দিতে সুবিধা হয়') }}"></textarea>
        </div>

        @include('community.partials.photo-picker', ['class' => 'mt-4'])

        <fieldset class="mt-5">
            <legend class="text-sm font-semibold">{{ __('বিষয়') }} <span class="font-medium text-ink-2">{{ __('(ঐচ্ছিক)') }}</span></legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($categories as $c)
                    <button type="button" class="filter-chip" :class="category === '{{ $c['slug'] }}' && 'is-on'" :aria-pressed="category === '{{ $c['slug'] }}'"
                        @click="category = category === '{{ $c['slug'] }}' ? '' : '{{ $c['slug'] }}'">{{ $c['emoji'] }} {{ $c['name'] }}</button>
                @endforeach
            </div>
            <input type="hidden" name="category" :value="category">
        </fieldset>

        <div class="mt-5">
            <label for="ask-area" class="text-sm font-semibold">{{ __('📍 কোন এলাকা নিয়ে?') }} <span class="font-medium text-ink-2">{{ __('(ঐচ্ছিক)') }}</span></label>
            <select id="ask-area" name="area" class="field mt-1">
                <option value="">{{ __('সারা বাংলাদেশ / নির্দিষ্ট এলাকা না') }}</option>
                @foreach ($districts as $division => $list)
                    <optgroup label="{{ __(':name বিভাগ', ['name' => $division]) }}">
                        @foreach ($list as $a)
                            <option value="{{ $a['slug'] }}" @selected($prefill['area'] === $a['slug'])>{{ $a['name'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <template x-if="signedIn && !myName">
            <div class="mt-5">
                <label for="ask-name" class="text-sm font-semibold">{{ __('আপনার নাম') }} <span class="font-medium text-ink-2">{{ __('(সবাই দেখবে)') }}</span></label>
                <input id="ask-name" name="name" maxlength="20" autocomplete="given-name" required class="field mt-1" placeholder="{{ __('যেমন: রাশেদ') }}">
            </div>
        </template>
        @include('community.partials.anon-toggle', ['class' => 'mt-5', 'label' => __('বেনামী হিসেবে পোস্ট করুন')])
        <p x-show="signedIn && (myName || anon)" x-cloak class="mt-3 text-sm text-ink-2">
            <span x-text="t('পোস্ট হবে :name নামে', { name: anon ? t('বেনামী') : myName })"></span>
        </p>

        <p role="alert" x-show="formError" x-cloak class="mt-3 text-sm font-semibold text-danger" x-text="formError"></p>
        <button type="submit" class="btn-primary mt-5" :disabled="busy || title.trim().length < 8"
            x-text="busy ? t('পোস্ট হচ্ছে…') : (type === 'question' || type === 'help' ? t('প্রশ্নটা পোস্ট করুন') : t('পোস্ট করুন'))">{{ __('প্রশ্নটা পোস্ট করুন') }}</button>
        <p class="mt-3 text-center text-xs leading-relaxed text-ink-2">{{ __('সম্মান রেখে লিখুন · কারও ফোন নম্বর বা ঠিকানা প্রকাশ্যে দেবেন না') }}</p>
        <noscript><p class="mt-2 text-center text-sm text-danger">{{ __('পোস্ট করতে ব্রাউজারে JavaScript চালু রাখুন।') }}</p></noscript>
    </form>
</div>
@endsection
