<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · তোমার বাংলাদেশ কোথায়?</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="min-h-dvh">
@hasSection('bare')
    @yield('content')
@else
    @php
        // [route, label, active pattern, svg path (24px, stroked)]
        $nav = [
            ['admin.dashboard', 'Analytics', 'admin.dashboard', 'M4 19V9m6 10V5m6 14v-7m4 7H2'],
            ['admin.questions.index', 'Questions', 'admin.questions.*', 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01'],
            ['admin.locations.index', 'Locations', 'admin.locations.*', 'M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
            ['admin.balance', 'Balance', 'admin.balance', 'M12 4v16M5 8h14M5 8l-3 7a3.5 3.5 0 0 0 6 0L5 8Zm14 0-3 7a3.5 3.5 0 0 0 6 0l-3-7ZM8 20h8'],
            ['admin.password.edit', 'Password', 'admin.password.*', 'M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5V11Zm7 4v2'],
        ];
        $icon = fn ($d) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$d.'"/></svg>';
    @endphp
    @auth
        <div class="lg:grid lg:grid-cols-[15rem_1fr]">
            {{-- Desktop sidebar --}}
            <aside class="sticky top-0 hidden h-dvh flex-col border-r border-line bg-card px-4 py-5 lg:flex">
                <a href="{{ route('admin.dashboard') }}" class="mb-8 flex items-center gap-3 px-2">
                    <span class="grid size-10 place-items-center rounded-xl bg-flag-green"><span class="size-4 rounded-full bg-flag-red"></span></span>
                    <span class="leading-tight"><span class="block font-bold">আমার বাংলাদেশ</span><span class="text-xs text-ink-2">Admin panel</span></span>
                </a>
                <nav class="space-y-1">
                    @foreach ($nav as [$route, $label, $pattern, $path])
                        <a href="{{ route($route) }}" @class(['nav-link', 'is-on' => request()->routeIs($pattern)])>{!! $icon($path) !!}{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="mt-auto space-y-1 border-t border-line pt-4">
                    <a href="{{ route('home') }}" target="_blank" class="nav-link">{!! $icon('M14 4h6v6M20 4l-9 9M18 14v6H4V6h6') !!}View site</a>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="nav-link w-full">{!! $icon('M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10') !!}Log out</button></form>
                    <p class="truncate px-3 pt-2 text-xs text-ink-2" title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</p>
                </div>
            </aside>

            <div class="min-w-0">
                {{-- Phone / tablet header with scrollable tabs --}}
                <header class="sticky top-0 z-20 border-b border-line bg-paper/90 backdrop-blur lg:hidden">
                    <div class="flex items-center justify-between px-4 pt-3">
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 font-bold">
                            <span class="grid size-8 place-items-center rounded-lg bg-flag-green"><span class="size-3 rounded-full bg-flag-red"></span></span>Admin
                        </a>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('home') }}" target="_blank" class="nav-link !px-2" aria-label="View site">{!! $icon('M14 4h6v6M20 4l-9 9M18 14v6H4V6h6') !!}</a>
                            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="nav-link !px-2" aria-label="Log out">{!! $icon('M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10') !!}</button></form>
                        </div>
                    </div>
                    <nav class="flex gap-1 overflow-x-auto px-3 py-2 [scrollbar-width:none]">
                        @foreach ($nav as [$route, $label, $pattern, $path])
                            <a href="{{ route($route) }}" @class(['nav-link !gap-2 !py-1.5', 'is-on' => request()->routeIs($pattern)])>{!! $icon($path) !!}{{ $label }}</a>
                        @endforeach
                    </nav>
                </header>

                <main class="mx-auto max-w-6xl px-4 py-6 pb-28 md:px-8 md:py-8">
                    @if ($errors->any())
                        <div role="alert" class="mb-5 rounded-2xl border border-flag-red/30 bg-flag-red/10 px-4 py-3 text-sm text-flag-red">
                            <p class="font-bold">Please fix the following:</p>
                            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>

        @if (session('status'))
            <div data-toast role="status" class="fixed right-4 bottom-4 z-50 flex items-center gap-2 rounded-2xl bg-ink px-4 py-3 text-sm font-semibold text-paper shadow-xl transition duration-300">
                <span class="grid size-5 place-items-center rounded-full bg-flag-green text-xs text-white">✓</span>{{ session('status') }}
            </div>
        @endif
    @else
        <main class="mx-auto max-w-6xl px-4 py-6">@yield('content')</main>
    @endauth
@endif
</body>
</html>
