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
    <div class="flex flex-wrap items-center gap-2">
        <nav class="flex gap-1 rounded-xl border border-line bg-card p-1 text-sm font-semibold" aria-label="Date range">
            @foreach ($ranges as $key => $label)
                <a href="?days={{ $key }}" @class(['rounded-lg px-3 py-1.5 transition', 'bg-ink text-paper' => $days == $key, 'text-ink-2 hover:text-ink' => $days != $key]) @if ($days == $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <details class="relative">
            <summary class="btn-outline cursor-pointer list-none !min-h-10 text-sm [&::-webkit-details-marker]:hidden">⬇ Export</summary>
            <div class="absolute right-0 z-20 mt-1 w-56 rounded-xl border border-line bg-card p-1 text-sm shadow-xl">
                <a href="{{ route('admin.export', ['type' => 'results', 'days' => $days]) }}" class="block rounded-lg px-3 py-2 hover:bg-paper-2"><b>Plays</b> <span class="text-ink-2">· one row per result</span></a>
                <a href="{{ route('admin.export', ['type' => 'daily', 'days' => $days]) }}" class="block rounded-lg px-3 py-2 hover:bg-paper-2"><b>Daily funnel</b> <span class="text-ink-2">· visitors, completed, shared</span></a>
                <p class="px-3 pt-1 pb-2 text-xs text-ink-2">CSV for {{ strtolower($ranges[$days]) }}, opens in Excel or Sheets.</p>
            </div>
        </details>
    </div>
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
                    @if ($step['key'] === 'completed') · {{ $fmt($s['plays']) }} {{ \Illuminate\Support\Str::plural('play', $s['plays']) }} @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>

{{-- Where the landing → play leak is: people arriving directly vs from a friend's result --}}
<section class="panel mt-4">
    <h2 class="panel-title">Start rate by entry <small>visitors who pressed play</small></h2>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['direct' => 'Landing page', 'link' => "Friend's shared link"] as $key => $label)
            @php($e = $entries[$key])
            <div>
                <div class="flex justify-between text-sm"><span>{{ $label }}</span><span class="tabular-nums text-ink-2"><b class="text-ink">{{ $fmt($e['started']) }}</b> of {{ $fmt($e['visitors']) }} · {{ $e['rate'] }}%</span></div>
                <div class="mt-1.5 h-2 rounded-full bg-paper-2"><div class="h-full rounded-full bg-flag-green" style="width: {{ $e['rate'] }}%"></div></div>
            </div>
        @endforeach
    </div>
</section>

<div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="stat"><div class="text-xs font-semibold text-ink-2">From shared links</div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['referral_visitors']) }}</div><div class="text-xs text-ink-2">visitors via a friend's result</div></div>
    <div class="stat"><div class="text-xs font-semibold text-ink-2">Played via links</div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['referred_players']) }}</div><div class="text-xs text-ink-2">people · {{ $s['referral_conversion'] }}% of link visitors</div></div>
    <div class="stat"><div class="text-xs font-semibold text-ink-2">Total plays</div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['plays']) }}</div><div class="text-xs text-ink-2">incl. replays</div></div>
    <div class="stat"><div class="flex items-center justify-between text-xs font-semibold text-ink-2">Viral coefficient <span class="pill {{ $s['viral_k'] >= 1 ? $tone['up'] : $tone['flat'] }}">{{ $s['viral_k'] >= 1 ? 'self-growing' : 'K < 1' }}</span></div><div class="mt-1 text-2xl font-bold tabular-nums">{{ $s['viral_k'] }}</div><div class="text-xs text-ink-2">new players per original player (people, not plays)</div></div>
</div>

<section class="panel mt-4">
    <h2 class="panel-title">Trend <small>{{ $days === '1' ? 'last 7 days' : $ranges[$days] }}</small></h2>
    @include('admin.partials.trend-chart', ['rows' => $trend])
</section>

<section class="panel mt-4">
    <h2 class="panel-title">When people play <small>plays per hour · {{ $ranges[$days] }}</small></h2>
    @include('admin.partials.hourly-heatmap')
</section>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <section class="panel">
        <h2 class="panel-title">Result distribution <small>@php($n = array_sum(array_column($distribution, 'count'))){{ $fmt($n) }} {{ \Illuminate\Support\Str::plural('result', $n) }}</small></h2>
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
<div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
    <section class="panel">
        <h2 class="panel-title"><span>Recent plays <small>newest first · tap to open the shared page</small></span><a href="{{ route('admin.plays', ['days' => $days]) }}" class="shrink-0 text-sm font-semibold text-green-text hover:underline">All plays →</a></h2>
        <ul class="divide-y divide-line text-sm">
            @forelse ($recent as $play)
                <li>
                    <a href="{{ route('results.show', $play['code']) }}" target="_blank" rel="noopener" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 transition hover:bg-paper-2">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper-2 text-lg">{{ $play['emoji'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $play['place'] }} <span class="font-normal text-ink-2">· {{ $play['pct'] }}%</span></span>
                            <span class="block truncate text-xs text-ink-2">{{ $play['name'] ?: 'No name' }}@if ($play['friend']) · <span class="text-green-text">via a friend's link</span>@endif</span>
                        </span>
                        <time class="shrink-0 text-xs text-ink-2" datetime="{{ $play['at']->toIso8601String() }}" title="{{ $play['at']->format('j M Y, g:i a') }}">{{ $play['at']->locale('en')->diffForHumans(short: true) }}</time>
                    </a>
                </li>
            @empty
                <li class="py-2 text-ink-2">No plays in this period yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel-title">Places explored <small>opened from results</small></h2>
        <ul class="divide-y divide-line text-sm">
            @forelse ($opens as $o)
                <li class="flex justify-between py-2.5"><span>{{ $o['emoji'] }} {{ $o['name'] }}</span><b class="tabular-nums">{{ $fmt($o['count']) }}</b></li>
            @empty
                <li class="py-2 text-ink-2">No data yet. Players open places by tapping them on their result page.</li>
            @endforelse
        </ul>
    </section>
</div>

<p class="mt-6 text-xs text-ink-2">Visitors are anonymous browser ids; no cookies, IPs or personal data are stored. Funnel steps count people; "plays" counts every saved result.</p>
@endsection
