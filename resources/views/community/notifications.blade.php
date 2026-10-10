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

        {{-- Settings: all email on/off (what the unsubscribe link turns off), then each kind on the site and by email --}}
        <section x-show="notices.prefs" x-cloak class="mt-8 rounded-3xl border border-line bg-card p-4" aria-labelledby="notice-settings-title">
            <h2 id="notice-settings-title" class="text-lg font-bold">{{ __('কী নিয়ে জানাবো') }}</h2>
            <template x-if="notices.email?.address">
                <label class="mt-3 flex items-center justify-between gap-4 border-b border-line pb-4">
                    <span>
                        <span class="block font-semibold">{{ __('ইমেইলেও জানান') }}</span>
                        <span class="block text-sm text-ink-2">{{ __('নতুন উত্তর এলে কয়েক মিনিট পরে একটা ইমেইল। একই পোস্টের জন্য ৬ ঘণ্টায় একটার বেশি না।') }}</span>
                    </span>
                    <input type="checkbox" role="switch" class="peer sr-only" :checked="notices.email.on" @change="setEmailNotices($el.checked)">
                    <span class="switch" aria-hidden="true"></span>
                </label>
            </template>
            <template x-if="notices.email && !notices.email.address">
                <p class="mt-2 text-sm text-ink-2">{{ __('আপনার অ্যাকাউন্টে কোনো ইমেইল নেই, তাই শুধু এখানেই জানাবো। ইমেইলে পেতে চাইলে Google বা ইমেইল দিয়ে লগইন করুন।') }}</p>
            </template>

            <div class="mt-2 divide-y divide-line">
                @foreach ([
                    'answer' => [__('আমার পোস্টে নতুন উত্তর'), true],
                    'reply' => [__('আমার উত্তরে কেউ জবাব দিলে'), false],
                    'accepted' => [__('আমার উত্তর সমাধান হিসেবে বাছাই হলে'), true],
                    'need' => [__('যে প্রশ্নে “আমারও জানা দরকার” দিয়েছি, তার উত্তর এলে'), false],
                ] as $type => [$label, $emailed])
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3">
                        <span id="notice-{{ $type }}" class="min-w-0 flex-1 basis-48 font-semibold">{{ $label }}</span>
                        <span class="flex shrink-0 items-center gap-4 text-sm text-ink-2">
                            <label class="flex items-center gap-2">
                                {{ __('সাইটে') }}
                                <input type="checkbox" role="switch" class="peer sr-only" aria-describedby="notice-{{ $type }}" :checked="notices.prefs?.site.{{ $type }}" @change="setNoticePref('site', '{{ $type }}', $el.checked)">
                                <span class="switch" aria-hidden="true"></span>
                            </label>
                            @if ($emailed)
                                <label class="flex items-center gap-2" x-show="notices.email?.address" :class="!(notices.email?.on && notices.prefs?.site.{{ $type }}) && 'opacity-50'">
                                    {{ __('ইমেইলে') }}
                                    <input type="checkbox" role="switch" class="peer sr-only" aria-describedby="notice-{{ $type }}" :disabled="!(notices.email?.on && notices.prefs?.site.{{ $type }})" :checked="notices.prefs?.email.{{ $type }}" @change="setNoticePref('email', '{{ $type }}', $el.checked)">
                                    <span class="switch" aria-hidden="true"></span>
                                </label>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-ink-2">{{ __('সাইটে বন্ধ করলে ইমেইলও যাবে না। জবাব আর “আমারও জানা দরকার”-এর খবর শুধু সাইটে আসে।') }}</p>
        </section>
    </div>
</div>
@endsection
