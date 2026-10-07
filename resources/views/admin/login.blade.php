@extends('layouts.admin')
@section('title', 'Log in')
@section('content')
<form method="POST" action="{{ route('admin.login') }}" class="mx-auto mt-16 max-w-sm space-y-4 rounded-3xl border border-line bg-card p-6">
    @csrf
    <h1 class="text-xl font-bold">🇧🇩 Admin log in</h1>
    <label class="block text-sm font-medium">Email
        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="input mt-1">
    </label>
    <label class="block text-sm font-medium">Password
        <input type="password" name="password" required class="input mt-1">
    </label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Remember me</label>
    <button class="btn-primary">Log in</button>
</form>
@endsection
