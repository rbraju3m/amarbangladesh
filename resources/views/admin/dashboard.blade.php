@extends('layouts.admin')
@section('title', 'Analytics')
@section('content')
@php
    $s = $summary;
    $fmt = fn ($n) => number_format($n);
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Growth funnel</h1>
    <div class="flex gap-1 rounded-xl border border-line p-1 text-sm">
        @foreach ($ranges as $key => $label)
            <a href="?days={{ $key }}" @class(['rounded-lg px-3 py-1.5', 'bg-ink text-paper' => $days == $key])>{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    <div class="stat"><div class="text-sm text-ink-2">Visitors</div><div class="text-3xl font-bold">{{ $fmt($s['visitors']) }}</div></div>
    <div class="stat"><div class="text-sm text-ink-2">Started quiz</div><div class="text-3xl font-bold">{{ $fmt($s['started']) }}</div><div class="text-sm text-ink-2">{{ $s['start_rate'] }}% of visitors</div></div>
    <div class="stat"><div class="text-sm text-ink-2">Completed</div><div class="text-3xl font-bold">{{ $fmt($s['completed']) }}</div><div class="text-sm text-ink-2">{{ $s['completion_rate'] }}% completion</div></div>
    <div class="stat"><div class="text-sm text-ink-2">Results shared</div><div class="text-3xl font-bold">{{ $fmt($s['shared']) }}</div><div class="text-sm text-ink-2">{{ $s['share_rate'] }}% share rate</div></div>
    <div class="stat"><div class="text-sm text-ink-2">Visitors from shared links</div><div class="text-3xl font-bold">{{ $fmt($s['referral_visitors']) }}</div></div>
    <div class="stat"><div class="text-sm text-ink-2">Completed via shared links</div><div class="text-3xl font-bold">{{ $fmt($s['referred_completed']) }}</div><div class="text-sm text-ink-2">{{ $s['referral_conversion'] }}% of link visitors</div></div>
    <div class="stat md:col-span-2"><div class="text-sm text-ink-2">Viral coefficient (K)</div><div class="text-3xl font-bold">{{ $s['viral_k'] }}</div><div class="text-sm text-ink-2">New players per original player. Above 1 means it grows on its own.</div></div>
</div>

<div class="mt-6 grid gap-4 md:grid-cols-3">
    <section class="stat">
        <h2 class="mb-3 font-bold">Result distribution</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($distribution as $row)
                <li>
                    <div class="flex justify-between"><span>{{ $row['emoji'] }} {{ $row['name'] }}</span><span class="tabular-nums text-ink-2">{{ $fmt($row['count']) }} · {{ $row['pct'] }}%</span></div>
                    <div class="mt-1 h-2 rounded-full bg-paper-2"><div class="h-full rounded-full bg-flag-green" style="width: {{ $row['pct'] }}%"></div></div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="stat">
        <h2 class="mb-3 font-bold">Question drop-off</h2>
        @php($top = max(array_values($reach) ?: [0]) ?: 1)
        <ul class="space-y-2 text-sm">
            @forelse ($reach as $q => $n)
                <li>
                    <div class="flex justify-between"><span>Answered Q{{ $q }}</span><span class="tabular-nums text-ink-2">{{ $fmt($n) }} · {{ round(100 * $n / $top) }}%</span></div>
                    <div class="mt-1 h-2 rounded-full bg-paper-2"><div class="h-full rounded-full bg-flag-red" style="width: {{ 100 * $n / $top }}%"></div></div>
                </li>
            @empty
                <li class="text-ink-2">No data yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="stat">
        <h2 class="mb-3 font-bold">Share actions</h2>
        <ul class="divide-y divide-line text-sm">
            @forelse (array_filter($channels) as $channel => $n)
                <li class="flex justify-between py-2"><span>{{ ['sheet' => 'Opened share sheet', 'native' => 'Native share (story)', 'link_copied' => 'Copied link', 'card_saved' => 'Saved card'][$channel] ?? ucfirst($channel) }}</span><span class="tabular-nums">{{ $fmt($n) }}</span></li>
            @empty
                <li class="py-2 text-ink-2">No data yet.</li>
            @endforelse
        </ul>
    </section>
</div>
<p class="mt-6 text-xs text-ink-2">Visitors are anonymous browser ids; no cookies, IPs or personal data are stored. "Completed" counts saved results.</p>
@endsection
