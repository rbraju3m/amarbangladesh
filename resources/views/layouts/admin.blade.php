<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · তোমার বাংলাদেশ কোথায়?</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh">
    @auth
        <nav class="sticky top-0 z-10 border-b border-line bg-paper/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center gap-1 overflow-x-auto px-4 py-2 text-sm font-medium">
                <span class="mr-3 font-bold whitespace-nowrap">🇧🇩 Admin</span>
                @foreach (['admin.dashboard' => 'Analytics', 'admin.questions.index' => 'Questions', 'admin.locations.index' => 'Locations', 'admin.balance' => 'Balance'] as $route => $label)
                    <a href="{{ route($route) }}" @class(['rounded-xl px-3 py-2 whitespace-nowrap', 'bg-ink text-paper' => request()->routeIs(str_replace('.index', '.*', $route)), 'hover:bg-paper-2' => ! request()->routeIs(str_replace('.index', '.*', $route))])>{{ $label }}</a>
                @endforeach
                <a href="{{ route('home') }}" target="_blank" class="ml-auto rounded-xl px-3 py-2 whitespace-nowrap hover:bg-paper-2">View site ↗</a>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="rounded-xl px-3 py-2 hover:bg-paper-2">Log out</button></form>
            </div>
        </nav>
    @endauth
    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-2xl bg-flag-green/10 px-4 py-3 text-sm font-medium text-green-text">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-2xl bg-flag-red/10 px-4 py-3 text-sm text-flag-red">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
