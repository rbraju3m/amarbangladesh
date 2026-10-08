@extends('community.layout', [
    'active' => 'me',
    'pageTitle' => __('আমি · আমার বাংলাদেশ'),
    'noindex' => true,
])

{{-- Forwards to the signed-in member's page; signed out, it offers sign-in. --}}
@section('main')
<div class="mx-auto max-w-md px-4 pt-10 text-center" x-init="openMe()">
    <div x-show="!signedIn" x-cloak>
        <p class="text-5xl" aria-hidden="true">👋</p>
        <h1 class="mt-3 text-2xl font-bold">{{ __('আপনার পাতা') }}</h1>
        <p class="mt-2 text-ink-2">{{ __('লগইন করলে এখানে আপনার প্রশ্ন, উত্তর আর কতজনের কাজে লেগেছে তা দেখবেন।') }}</p>
        <button type="button" class="btn-primary mt-6" @click="openLogin().then(() => openMe(), () => {})">{{ __('লগইন করুন') }}</button>
        <a href="{{ lroute('feed', [], false) }}" class="btn-ghost mt-3 w-full">{{ __('আলোচনা দেখি') }}</a>
    </div>
    <p x-show="signedIn" class="text-ink-2">{{ __('আপনার পাতা খুলছি…') }}</p>
</div>
@endsection
