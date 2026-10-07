@extends('layouts.admin')
@section('title', 'Balance')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Result balance</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">
        Every possible answer combination ({{ number_format($combinations) }}) is played through the current scoring.
        Each place should win between {{ $min }}% and {{ $max }}% of them — otherwise some results almost never appear, or appear too often.
    </p>
</header>

@if ($tooMany)
    <p class="rounded-2xl bg-flag-red/10 p-4 text-sm text-flag-red">Too many combinations to simulate here. Use <code>php artisan quiz:simulate</code>.</p>
@else
    @php
        $scale = 25; // bar width: 25% of combinations fills the track
        $out = collect($report['share'])->filter(fn ($v) => $v < $min || $v > $max);
    @endphp
    <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
        <section class="panel">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">Share of combinations won</h2>
                @if ($out->isEmpty())
                    <span class="pill bg-flag-green/10 text-green-text">✓ All {{ count($report['share']) }} places in range</span>
                @else
                    <span class="pill bg-flag-red/10 text-flag-red">⚠ {{ $out->count() }} out of range</span>
                @endif
            </div>
            <ul class="space-y-4">
                @foreach ($report['share'] as $slug => $share)
                    @php($ok = $share >= $min && $share <= $max)
                    <li>
                        <div class="flex justify-between text-sm font-semibold">
                            <span>{{ $locations[$slug]->emoji ?? '' }} {{ $locations[$slug]->name_bn ?? $slug }}</span>
                            <span @class(['tabular-nums', 'text-flag-red' => ! $ok])>{{ $share }}%{{ $ok ? '' : ($share < $min ? ' · too rare' : ' · too common') }}</span>
                        </div>
                        <div class="relative mt-1.5 h-3 rounded-full bg-paper-2">
                            {{-- healthy band --}}
                            <div class="absolute inset-y-0 rounded-full bg-flag-green/15 ring-1 ring-flag-green/30" style="left: {{ $min / $scale * 100 }}%; width: {{ ($max - $min) / $scale * 100 }}%"></div>
                            <div @class(['relative h-full rounded-full', 'bg-flag-green' => $ok, 'bg-flag-red' => ! $ok]) style="width: {{ min(100, $share / $scale * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex items-center gap-2 text-xs text-ink-2"><i class="inline-block h-3 w-6 rounded-full bg-flag-green/15 ring-1 ring-flag-green/30"></i> healthy band {{ $min }}–{{ $max }}% · track ends at {{ $scale }}%</div>
        </section>

        <aside class="space-y-4">
            <section class="panel">
                <h2 class="font-bold">Displayed match %</h2>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-paper-2 p-2"><dt class="text-xs text-ink-2">Min</dt><dd class="text-xl font-bold tabular-nums">{{ $report['match']['min'] }}</dd></div>
                    <div class="rounded-xl bg-paper-2 p-2"><dt class="text-xs text-ink-2">Avg</dt><dd class="text-xl font-bold tabular-nums">{{ $report['match']['avg'] }}</dd></div>
                    <div class="rounded-xl bg-paper-2 p-2"><dt class="text-xs text-ink-2">Max</dt><dd class="text-xl font-bold tabular-nums">{{ $report['match']['max'] }}</dd></div>
                </dl>
            </section>
            <section class="panel text-sm text-ink-2">
                <h2 class="mb-1 font-bold text-ink">Fixing an imbalance</h2>
                Nudge the place's trait profile in <a href="{{ route('admin.locations.index') }}" class="font-semibold text-ink underline">Locations</a>, or an answer's place bonus in <a href="{{ route('admin.questions.index') }}" class="font-semibold text-ink underline">Questions</a>, then come back here.
            </section>
        </aside>
    </div>
@endif
@endsection
