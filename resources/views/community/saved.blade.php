@extends('community.layout', [
    'active' => 'me',
    'pageTitle' => __('সেভ করা পোস্ট · আমার বাংলাদেশ'),
    'noindex' => true,
])

{{-- A cacheable shell: the list is the signed-in member's (private), fetched with their token. --}}
@section('main')
<div class="mx-auto max-w-2xl px-4 pt-6" x-init="loadSaved()">
    <h1 class="text-2xl font-bold">🔖 {{ __('সেভ করা পোস্ট') }}</h1>
    <p class="mt-1 text-sm text-ink-2">{{ __('শুধু আপনি দেখতে পান।') }}</p>

    <div x-show="!signedIn" x-cloak class="mt-8 text-center">
        <p class="text-5xl" aria-hidden="true">🔖</p>
        <p class="mt-3 text-ink-2">{{ __('লগইন করলে পরে পড়ার জন্য পোস্ট সেভ করে রাখতে পারবেন।') }}</p>
        <button type="button" class="btn-primary mt-6" @click="openLogin().then(() => loadSaved(), () => {})">{{ __('লগইন করুন') }}</button>
    </div>

    <div x-show="signedIn" x-cloak>
        <p x-show="savedList.state === 'loading'" class="mt-6 text-ink-2">{{ __('আনছি…') }}</p>
        <p x-show="savedList.state === 'error'" class="mt-6 text-ink-2" role="alert">{{ __('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।') }}</p>

        <div x-show="savedList.state === 'empty'" class="mt-6 rounded-3xl border border-dashed border-line px-5 py-8 text-center">
            <p class="text-4xl" aria-hidden="true">🔖</p>
            <p class="mt-2 font-semibold">{{ __('এখনো কিছু সেভ করেননি') }}</p>
            <p class="mt-1 text-sm text-ink-2">{{ __('কোনো পোস্টে 🔖 চাপলে সেটা এখানে থাকবে, পরে পড়ার জন্য।') }}</p>
            <a href="{{ lroute('feed', [], false) }}" class="btn-ghost mt-4 w-full">{{ __('আলোচনা দেখি') }}</a>
        </div>

        <h2 class="sr-only">{{ __('সেভ করা পোস্ট') }}</h2>
        <div id="saved-list" class="mt-4 space-y-3"></div>
        <button type="button" x-show="savedList.next" class="btn-ghost mt-4 w-full" :disabled="busy" @click="loadSaved(true)">{{ __('আরও দেখুন') }}</button>
    </div>
</div>
@endsection
