@extends('layouts.admin')
@section('title', 'Password')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Password</h1>
    <p class="mt-1 text-sm text-ink-2">Logged in as <b class="text-ink">{{ auth()->user()->email }}</b>.</p>
</header>
<form method="POST" action="{{ route('admin.password.update') }}" class="panel max-w-md space-y-4">
    @csrf @method('PUT')
    <h2 class="font-bold">Change password</h2>
    <label class="block"><span class="field-label">Current password</span>
        <input type="password" name="current_password" required autocomplete="current-password" class="input">
    </label>
    <label class="block"><span class="field-label">New password <span class="normal-case">at least 10 characters</span></span>
        <input type="password" name="password" required minlength="10" autocomplete="new-password" class="input">
    </label>
    <label class="block"><span class="field-label">Repeat new password</span>
        <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password" class="input">
    </label>
    <button class="btn">Update password</button>
</form>
@endsection
