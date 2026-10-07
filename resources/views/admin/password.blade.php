@extends('layouts.admin')
@section('title', 'Password')
@section('content')
<form method="POST" action="{{ route('admin.password.update') }}" class="max-w-md space-y-4 rounded-3xl border border-line bg-card p-6">
    @csrf @method('PUT')
    <div>
        <h1 class="text-xl font-bold">Change password</h1>
        <p class="mt-1 text-sm text-ink-2">Logged in as {{ auth()->user()->email }}. At least 10 characters.</p>
    </div>
    <label class="block text-sm font-medium">Current password
        <input type="password" name="current_password" required autocomplete="current-password" class="input mt-1">
    </label>
    <label class="block text-sm font-medium">New password
        <input type="password" name="password" required minlength="10" autocomplete="new-password" class="input mt-1">
    </label>
    <label class="block text-sm font-medium">Repeat new password
        <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password" class="input mt-1">
    </label>
    <button class="inline-flex min-h-11 items-center rounded-2xl bg-ink px-5 font-semibold text-paper">Update password</button>
</form>
@endsection
