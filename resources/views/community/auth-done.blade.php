@extends('community.layout', ['active' => 'me', 'pageTitle' => __('লগইন হচ্ছে…').' · '.__('আমার বাংলাদেশ'), 'noindex' => true])

{{-- Google/Facebook return here with #t=token (or #error=…); the browser stores it and goes back. --}}
@section('main')
<div class="mx-auto max-w-md px-4 pt-16 text-center" x-init="finishSocialLogin()">
    <p x-show="!authError" class="text-ink-2">{{ __('লগইন হচ্ছে…') }}</p>
    <div x-show="authError" x-cloak>
        <p class="text-4xl" aria-hidden="true">😕</p>
        <p class="mt-3 font-bold" x-text="authError"></p>
        <button type="button" class="btn-primary mt-6" @click="openLogin()">{{ __('আবার চেষ্টা করুন') }}</button>
    </div>
</div>
@endsection
