@extends('layouts.admin')
@section('title', 'Log in')
@section('bare', true)
@section('content')
<div class="grid min-h-dvh lg:grid-cols-2">
    {{-- Brand panel (desktop) --}}
    <div class="relative hidden overflow-hidden bg-flag-green lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="absolute -right-24 top-1/2 size-[28rem] -translate-y-1/2 rounded-full bg-flag-red/90" aria-hidden="true"></div>
        <div class="relative flex items-center gap-3 text-white">
            <span class="rounded-full bg-white p-1">@include('partials.logo', ['class' => 'block size-10'])</span>
            <span class="text-lg font-bold">তোমার বাংলাদেশ কোথায়?</span>
        </div>
        <div class="relative max-w-sm text-white">
            <p class="text-4xl leading-tight font-bold">Run the quiz behind the passport.</p>
            <p class="mt-3 text-white/80">Analytics, questions, places and scoring balance.</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex items-center justify-center px-4 py-12">
        <form method="POST" action="{{ route('admin.login') }}" class="w-full max-w-sm">
            @csrf
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                @include('partials.logo', ['class' => 'size-11 shrink-0'])
                <span class="font-bold">তোমার বাংলাদেশ কোথায়?</span>
            </div>
            <h1 class="text-2xl font-bold">Welcome back</h1>
            <p class="mt-1 text-sm text-ink-2">Log in to the admin panel.</p>

            @if ($errors->any())
                <div role="alert" class="mt-6 rounded-2xl bg-flag-red/10 px-4 py-3 text-sm font-medium text-flag-red">{{ $errors->first() }}</div>
            @endif

            <label class="mt-6 block text-sm font-medium">Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    @class(['input mt-1.5 min-h-12 text-base', 'border-flag-red' => $errors->has('email')])>
            </label>
            <label class="mt-4 block text-sm font-medium">Password
                <span class="relative mt-1.5 block">
                    <input id="password" type="password" name="password" required autocomplete="current-password" class="input min-h-12 pr-16 text-base">
                    <button type="button" id="toggle-password" aria-controls="password" aria-pressed="false"
                        class="absolute inset-y-0 right-1.5 my-auto h-9 rounded-lg px-2.5 text-xs font-semibold text-ink-2 hover:bg-paper-2">Show</button>
                </span>
            </label>
            <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--green)]"> Keep me logged in</label>
            <button class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-flag-green px-6 font-semibold text-white transition hover:brightness-110 active:scale-[0.98]">Log in</button>
            <p class="mt-6 text-center text-xs text-ink-2">Forgot your password? Ask another admin, or run <code>php artisan tinker</code> on the server.</p>
        </form>
    </div>
</div>
<script>
    document.getElementById('toggle-password').addEventListener('click', (e) => {
        const input = document.getElementById('password');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        e.currentTarget.textContent = show ? 'Hide' : 'Show';
        e.currentTarget.setAttribute('aria-pressed', show);
    });
</script>
@endsection
