@php
    $siteTitle = 'তোমার বাংলাদেশ কোথায়?';
    $ogTitle = $ogTitle ?? $siteTitle.' 🇧🇩';
    $ogDescription = $ogDescription ?? 'বাংলাদেশের কোন জায়গাটা তোমার personality-এর সাথে সবচেয়ে বেশি মেলে? মাত্র ১ মিনিটে খুঁজে বের করো।';
    $ogImage = $ogImage ?? asset('images/og/default.png');
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle ?? $siteTitle }}</title>
    <meta name="description" content="{{ $ogDescription }}">
    <meta name="theme-color" content="#fbf8f1" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f1613" media="(prefers-color-scheme: dark)">
    @isset($noindex)<meta name="robots" content="noindex, follow">@endisset
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteTitle }}">
    <meta property="og:locale" content="bn_BD">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $ogTitle }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="preload" href="{{ Vite::asset('resources/fonts/anek-bangla-bengali-500-700.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @yield('content')
</body>
</html>
