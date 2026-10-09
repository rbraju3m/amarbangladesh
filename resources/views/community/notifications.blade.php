@extends('community.layout', [
    'active' => 'me',
    'pageTitle' => __('নোটিফিকেশন · আমার বাংলাদেশ'),
    'noindex' => true,
])

{{-- A cacheable shell: the list is the signed-in member's, fetched with their token. --}}
@section('main')
<div class="mx-auto max-w-2xl px-4 pt-6" x-init="loadNotifications()">
    <h1 class="text-2xl font-bold">{{ __('নোটিফিকেশন') }}</h1>

    <div x-show="!signedIn" x-cloak class="mt-8 text-center">
        <p class="text-5xl" aria-hidden="true">🔔</p>
        <p class="mt-3 text-ink-2">{{ __('লগইন করলে এখানে দেখবেন কে আপনার প্রশ্নের উত্তর দিলো।') }}</p>
        <button type="button" class="btn-primary mt-6" @click="openLogin().then(() => loadNotifications(), () => {})">{{ __('লগইন করুন') }}</button>
    </div>

    <div x-show="signedIn" x-cloak>
        <p x-show="notices.state === 'loading'" class="mt-6 text-ink-2">{{ __('আনছি…') }}</p>
        <p x-show="notices.state === 'error'" class="mt-6 text-ink-2">{{ __('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।') }}</p>

        <div x-show="notices.state === 'empty'" class="mt-6 rounded-3xl border border-dashed border-line px-5 py-8 text-center">
            <p class="text-4xl" aria-hidden="true">🔔</p>
            <p class="mt-2 font-semibold">{{ __('এখনো কোনো নোটিফিকেশন নেই') }}</p>
            <p class="mt-1 text-sm text-ink-2">{{ __('কেউ আপনার প্রশ্নের উত্তর দিলে বা আপনার উত্তর কারো সমাধান হলে এখানে জানাবো।') }}</p>
            <a href="{{ lroute('ask', [], false) }}" class="btn-ghost mt-4 w-full">{{ __('কিছু জিজ্ঞেস করুন') }}</a>
        </div>

        <div id="notice-list" class="mt-4 space-y-2"></div>
        <button type="button" x-show="notices.next" class="btn-ghost mt-4 w-full" :disabled="busy" @click="loadNotifications(true)">{{ __('আরও দেখুন') }}</button>

        {{-- Email setting --}}
        <div x-show="notices.email" class="mt-8 rounded-3xl border border-line bg-card p-4">
            <template x-if="notices.email?.address">
                <label class="flex items-center justify-between gap-4">
                    <span>
                        <span class="block font-semibold">{{ __('ইমেইলেও জানান') }}</span>
                        <span class="block text-sm text-ink-2">{{ __('নতুন উত্তর এলে কয়েক মিনিট পরে একটা ইমেইল। একই পোস্টের জন্য ৬ ঘণ্টায় একটার বেশি না।') }}</span>
                    </span>
                    <input type="checkbox" role="switch" class="peer sr-only" :checked="notices.email.on" @change="setEmailNotices($el.checked)">
                    <span class="switch" aria-hidden="true"></span>
                </label>
            </template>
            <template x-if="notices.email && !notices.email.address">
                <p class="text-sm text-ink-2">{{ __('আপনার অ্যাকাউন্টে কোনো ইমেইল নেই, তাই শুধু এখানেই জানাবো। ইমেইলে পেতে চাইলে Google বা ইমেইল দিয়ে লগইন করুন।') }}</p>
            </template>
        </div>
    </div>
</div>
@endsection
