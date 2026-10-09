{{-- Self-contained (inline CSS, no Vite, no DB) so it still renders when the app is broken or down. --}}
<!DOCTYPE html>
<html lang="{{ \App\Support\Lang::current() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ __('তোমার বাংলাদেশ কোথায়?') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --paper: #fbf8f1; --card: #fff; --ink: #14211b; --ink-2: #4b5a52; --line: #e4ddcc; --green: #006a4e; --red: #e03a3e; color-scheme: light; }
        @media (prefers-color-scheme: dark) {
            :root { --paper: #0f1613; --card: #17211c; --ink: #eef3ef; --ink-2: #a9b7ae; --line: #2a3830; --green: #3fb488; color-scheme: dark; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 24px 16px; background: var(--paper); color: var(--ink);
            font: 500 17px/1.6 'Anek Bangla Variable', 'Noto Sans Bengali', 'Hind Siliguri', system-ui, sans-serif; text-align: center; }
        main { max-width: 26rem; }
        .flag { width: 72px; height: 72px; margin: 0 auto 20px; border-radius: 20px; background: var(--green); display: grid; place-items: center; }
        .flag span { width: 32px; height: 32px; border-radius: 50%; background: var(--red); margin-left: -8px; }
        .code { font-size: 14px; letter-spacing: .1em; color: var(--ink-2); }
        h1 { margin: 4px 0 8px; font-size: 26px; line-height: 1.3; }
        p { margin: 0; color: var(--ink-2); }
        a { display: inline-block; margin-top: 24px; padding: 14px 24px; border-radius: 999px; background: var(--green); color: #fff; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <div class="flag" aria-hidden="true"><span></span></div>
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        @hasSection('noLink')
        @else
            <a href="{{ \App\Support\Lang::path('/quiz') }}">{{ __('কুইজটা খেলো →') }}</a>
        @endif
    </main>
</body>
</html>
