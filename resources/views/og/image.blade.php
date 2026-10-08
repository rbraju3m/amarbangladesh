{{-- 1200×630 link-preview image, screenshotted by `php artisan quiz:og-images`. --}}
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Anek'; src: url('{{ $fontBn }}') format('woff2'); font-weight: 100 800; unicode-range: U+0980-09FF, U+200C-200D, U+25CC; }
    @font-face { font-family: 'Anek'; src: url('{{ $fontLatin }}') format('woff2'); font-weight: 100 800; }
    * { box-sizing: border-box; margin: 0; }
    body { width: 1200px; height: 630px; overflow: hidden; font-family: 'Anek', 'Noto Color Emoji', sans-serif; background: #fbf8f1; color: #14211b; }
    .wrap { display: flex; height: 100%; }
    .art { width: 560px; height: 100%; position: relative; overflow: hidden; }
    .art img { width: 100%; height: 100%; object-fit: cover; }
    .art svg { width: 100%; height: 100%; padding: 40px 60px; }
    .text { flex: 1; min-width: 0; padding: 64px 56px 56px; display: flex; flex-direction: column; justify-content: center; }
    .kicker { font-size: 34px; font-weight: 500; color: #4b5a52; }
    .name { white-space: pre; font-size: {{ $nameSize ?? 112 }}px; font-weight: 700; line-height: 1.15; color: {{ $accent }}; margin-top: 6px; }
    .title { font-size: 40px; font-weight: 600; margin-top: 8px; }
    .cta { margin-top: auto; display: inline-flex; align-self: flex-start; background: #e03a3e; color: #fff; font-size: 34px; font-weight: 600; padding: 14px 30px; border-radius: 999px; }
    .flag { position: absolute; top: 28px; left: 28px; background: rgba(255,255,255,.92); border-radius: 999px; padding: 8px 22px; font-size: 26px; font-weight: 600; }
</style>
</head>
<body>
<div class="wrap">
    <div class="art" style="background: {{ $accent }}22">
        @if ($illustration)
            <img src="{{ $illustration }}" alt="">
            <div class="flag">{{ __('🇧🇩 বাংলাদেশ ভাইব পাসপোর্ট') }}</div>
        @else
            <svg viewBox="0 0 400 552"><path d="@include('partials.bd-map-path')" fill="#2f8a5f" stroke="#1f5f42" stroke-width="1.5"/></svg>
        @endif
    </div>
    <div class="text">
        <div class="kicker">{{ $kicker }}</div>
        <div class="name">{{ $name }}</div>
        <div class="title">{{ $title }}</div>
        <div class="cta">{{ $cta ?? __('তোমার বাংলাদেশ কোথায়? →') }}</div>
    </div>
</div>
</body>
</html>
