@extends('community.layout', ['active' => 'me', 'pageTitle' => __('নতুন পাসওয়ার্ড').' · '.__('আমার বাংলাদেশ'), 'noindex' => true])

{{-- The reset link carries its token in the #fragment, so it never reaches a server log. --}}
@section('main')
<div class="mx-auto max-w-md px-4 pt-10">
    <h1 class="text-2xl font-bold">{{ __('নতুন পাসওয়ার্ড দিন') }}</h1>
    <form class="mt-5" @submit.prevent="resetPassword($el)">
        <label for="new-password" class="text-sm font-semibold">{{ __('নতুন পাসওয়ার্ড') }} <span class="font-medium text-ink-2">({{ __('অন্তত ৮ অক্ষর') }})</span></label>
        <input id="new-password" name="password" type="password" minlength="8" required autocomplete="new-password" class="field mt-1">
        <p x-show="formError" x-cloak class="mt-2 text-sm font-semibold text-danger" x-text="formError"></p>
        <button class="btn-primary mt-4" :disabled="authBusy">{{ __('পাসওয়ার্ড সেভ করুন') }}</button>
    </form>
</div>
@endsection
