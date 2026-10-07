@extends('layouts.admin')
@section('title', 'Analytics')
@section('content')
@php
    $s = $summary;
    $p = $previous;
    $fmt = fn ($n) => number_format($n);
    // Change against the same length of time just before.
    $delta = function (string $key) use ($s, $p) {
        [$now, $before] = [$s[$key], $p[$key]];
        if ($now == $before) return ['text' => 'no change', 'tone' => 'flat'];
        if ($before == 0) return ['text' => 'new', 'tone' => 'up'];
        $pct = round(100 * ($now - $before) / $before);
        return ['text' => ($pct > 0 ? '▲ ' : '▼ ').abs($pct).'%', 'tone' => $pct > 0 ? 'up' : 'down'];
    };
    $tone = ['up' => 'bg-flag-green/10 text-green-text', 'down' => 'bg-flag-red/10 text-flag-red', 'flat' => 'bg-paper-2 text-ink-2'];
    $steps = [
        ['key' => 'visitors', 'label' => 'Visitors', 'hint' => 'opened the site'],
        ['key' => 'started', 'label' => 'Started', 'hint' => 'pressed play', 'rate' => $s['start_rate']],
        ['key' => 'completed', 'label' => 'Completed', 'hint' => 'saw a result', 'rate' => $s['completion_rate']],
        ['key' => 'shared', 'label' => 'Shared', 'hint' => 'results shared', 'rate' => $s['share_rate']],
    ];
@endphp

<header class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold md:text-3xl">Growth funnel</h1>
        <p class="mt-1 text-sm text-ink-2">How people move from opening the site to sharing their result. Arrows compare with the previous {{ strtolower($ranges[$days]) === 'today' ? 'day' : 'period' }}.</p>
    </div>
    <nav class="flex gap-1 rounded-xl border border-line bg-card p-1 text-sm font-semibold" aria-label="Date range">
        @foreach ($ranges as $key => $label)
            <a href="?days={{ $key }}" @class(['rounded-lg px-3 py-1.5 transition', 'bg-ink text-paper' => $days == $key, 'text-ink-2 hover:text-ink' => $days != $key]) @if ($days == $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</header>

{{-- The funnel as connected steps --}}
<section class="panel overflow-hidden !p-0">
    <ol class="grid grid-cols-2 md:grid-cols-4">
        @foreach ($steps as $i => $step)
            @php($d = $delta($step['key']))
            <li @class(['relative border-line p-5', 'border-l' => $i % 2 === 1, 'md:border-l md:pl-10' => $i > 0, 'border-t md:border-t-0' => $i > 1])>
                @if (isset($step['rate']))
                    <span class="absolute top-1/2 -left-3 z-[1] hidden -translate-y-1/2 rounded-full border border-line bg-card px-1.5 py-0.5 text-[11px] font-bold text-ink-2 tabular-nums md:block">{{ $step['rate'] }}%</span>
                @endif
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-ink-2">{{ $step['label'] }}</span>
                    <span class="pill {{ $tone[$d['tone']] }}">{{ $d['text'] }}</span>
                </div>
                <div class="mt-2 text-3xl font-bold tabular-nums md:text-4xl">{{ $fmt($s[$step['key']]) }}</div>
                <div class="mt-1 text-xs text-ink-2">
                    {{ $step['hint'] }}@isset($step['rate']) <span class="md:hidden">· {{ $step['rate'] }}%</span>@endisset
                    @if ($step['key'] === 'completed') · {{ $fmt($s['plays']) }} plays @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>

<div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="stat"><div class="text-xs font-semibold text-ink-2">From shared links</div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['referral_visitors']) }}</div><div class="text-xs text-ink-2">visitors via a friend's result</div></div>
    <div class="stat"><div class="text-xs font-semibold text-ink-2">Played via links</div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['referred_completed']) }}</div><div class="text-xs text-ink-2">{{ $s['referral_conversion'] }}% of link visitors</div></div>
    <div class="stat"><div class="text-xs font-semibold text-ink-2">Total plays</div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['plays']) }}</div><div class="text-xs text-ink-2">incl. replays</div></div>
    <div class="stat"><div class="flex items-center justify-between text-xs font-semibold text-ink-2">Viral coefficient <span class="pill {{ $s['viral_k'] >= 1 ? $tone['up'] : $tone['flat'] }}">{{ $s['viral_k'] >= 1 ? 'self-growing' : 'K < 1' }}</span></div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $s['viral_k'] }}</div><div class="text-xs text-ink-2">new players per original player</div></div>
</div>

<section class="panel mt-4">
    <h2 class="panel-title">Trend <small>{{ $days === '1' ? 'last 7 days' : $ranges[$days] }}</small></h2>
    @include('admin.partials.trend-chart', ['rows' => $trend])
</section>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <section class="panel">
        <h2 class="panel-title">Result distribution <small>{{ $fmt(array_sum(array_column($distribution, 'count'))) }} results</small></h2>
        <ul class="space-y-3 text-sm">
            @foreach ($distribution as $row)
                <li>
                    <div class="flex justify-between"><span>{{ $row['emoji'] }} {{ $row['name'] }}</span><span class="tabular-nums text-ink-2"><b class="text-ink">{{ $fmt($row['count']) }}</b> · {{ $row['pct'] }}%</span></div>
                    <div class="mt-1.5 h-2 rounded-full bg-paper-2"><div class="h-full rounded-full bg-flag-green" style="width: {{ $row['pct'] }}%"></div></div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel-title">Question drop-off <small>vs. question 1</small></h2>
        @php($top = max(array_values($reach) ?: [0]) ?: 1)
        <ul class="space-y-3 text-sm">
            @forelse ($reach as $q => $n)
                @php($share = round(100 * $n / $top))
                <li>
                    <div class="flex justify-between"><span>Question {{ $q }}</span><span class="tabular-nums text-ink-2"><b class="text-ink">{{ $fmt($n) }}</b> · {{ $share }}%</span></div>
                    <div class="mt-1.5 h-2 rounded-full bg-paper-2"><div @class(['h-full rounded-full', 'bg-flag-green' => $share >= 80, 'bg-[#eda100]' => $share < 80 && $share >= 60, 'bg-flag-red' => $share < 60]) style="width: {{ $share }}%"></div></div>
                </li>
            @empty
                <li class="text-ink-2">No data yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel-title">Share actions</h2>
        <ul class="divide-y divide-line text-sm">
            @forelse (array_filter($channels) as $channel => $n)
                <li class="flex justify-between py-2.5"><span>{{ ['sheet' => 'Opened share sheet', 'native' => 'Native share (story)', 'link_copied' => 'Copied link', 'card_saved' => 'Saved card', 'whatsapp' => 'WhatsApp'][$channel] ?? ucfirst($channel) }}</span><b class="tabular-nums">{{ $fmt($n) }}</b></li>
            @empty
                <li class="py-2 text-ink-2">No data yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel-title">Card designs <small>saved + native shares</small></h2>
        <ul class="divide-y divide-line text-sm">
            @forelse ($templates as $template => $n)
                <li class="flex justify-between py-2.5"><span>{{ ['passport' => '🛂 Passport', 'poster' => '🖼️ Poster', 'boarding' => '🎫 Boarding pass', 'minimal' => '✨ Minimal'][$template] ?? $template }}</span><b class="tabular-nums">{{ $fmt($n) }}</b></li>
            @empty
                <li class="py-2 text-ink-2">No data yet.</li>
            @endforelse
        </ul>
        @if ($formats)
            <p class="mt-3 border-t border-line pt-3 text-xs text-ink-2">Size: @foreach ($formats as $format => $n){{ ['story' => '📱 Story', 'square' => '⬜ Square'][$format] ?? $format }} <b class="tabular-nums text-ink">{{ $fmt($n) }}</b>@if (! $loop->last) · @endif @endforeach</p>
        @endif
    </section>

    <section class="panel">
        <h2 class="panel-title">Card colours <small>saved + native shares</small></h2>
        @php($swatch = ['place' => '#2f7d4f', 'cream' => '#b4532a', 'sunset' => '#e63971', 'ocean' => '#2356e8', 'night' => '#191835', 'emerald' => '#0c5a42'])
        <ul class="divide-y divide-line text-sm">
            @forelse ($themes as $theme => $n)
                <li class="flex justify-between py-2.5"><span class="flex items-center gap-2"><i class="inline-block size-3 rounded-full border border-line" style="background: {{ $swatch[$theme] ?? '#999' }}"></i>{{ ['place' => 'Place colour', 'cream' => 'Cream', 'sunset' => 'Sunset', 'ocean' => 'Ocean', 'night' => 'Night', 'emerald' => 'Emerald'][$theme] ?? $theme }}</span><b class="tabular-nums">{{ $fmt($n) }}</b></li>
            @empty
                <li class="py-2 text-ink-2">No data yet.</li>
            @endforelse
        </ul>
    </section>
</div>
<p class="mt-6 text-xs text-ink-2">Visitors are anonymous browser ids; no cookies, IPs or personal data are stored. Funnel steps count people; "plays" counts every saved result.</p>
@endsection
