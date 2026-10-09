@extends('community.layout', [
    'active' => 'me',
    'pageTitle' => __('ইমেইল বন্ধ · আমার বাংলাদেশ'),
    'noindex' => true,
])

{{-- From the link in a notification email (signed, no login). --}}
@section('main')
<div class="mx-auto max-w-md px-4 pt-10 text-center">
    <p class="text-5xl" aria-hidden="true">{{ $done ? '✅' : '✉️' }}</p>
    @if ($done)
        <h1 class="mt-3 text-2xl font-bold">{{ __('ইমেইল বন্ধ হয়েছে') }}</h1>
        <p class="mt-2 text-ink-2">{{ __('আর নোটিফিকেশন ইমেইল পাঠাবো না। নতুন উত্তর সাইটে “আমি”-তে দেখতে পাবেন।') }}</p>
    @else
        <h1 class="mt-3 text-2xl font-bold">{{ __('নোটিফিকেশন ইমেইল বন্ধ করবেন?') }}</h1>
        <p class="mt-2 text-ink-2">{{ __('নতুন উত্তর এলে আর ইমেইল পাবেন না। সাইটে তবু দেখতে পাবেন।') }}</p>
        <form method="post" action="{{ $action }}" class="mt-6">
            <button class="btn-primary">{{ __('হ্যাঁ, বন্ধ করুন') }}</button>
        </form>
    @endif
    <a href="{{ lroute('notifications', [], false) }}" class="btn-ghost mt-3 w-full">{{ __('নোটিফিকেশন দেখুন') }}</a>
</div>
@endsection
