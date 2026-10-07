@extends('layouts.admin')
@section('title', 'Balance')
@section('content')
<h1 class="text-2xl font-bold">Result balance</h1>
<p class="mt-1 max-w-2xl text-sm text-ink-2">
    Every possible answer combination ({{ number_format($combinations) }}) is played through the current scoring.
    Each place should win between {{ $min }}% and {{ $max }}% of them. Otherwise some results will almost never appear, or appear too often.
</p>

@if ($tooMany)
    <p class="mt-6 rounded-2xl bg-flag-red/10 p-4 text-sm text-flag-red">Too many combinations to simulate here. Use <code>php artisan quiz:simulate</code>.</p>
@else
    <div class="stat mt-6 max-w-2xl">
        <ul class="space-y-3">
            @foreach ($report['share'] as $slug => $share)
                @php($ok = $share >= $min && $share <= $max)
                <li>
                    <div class="flex justify-between text-sm font-medium">
                        <span>{{ $locations[$slug]->emoji ?? '' }} {{ $locations[$slug]->name_bn ?? $slug }}</span>
                        <span @class(['tabular-nums', 'text-flag-red font-bold' => ! $ok])>{{ $share }}% {{ $ok ? '' : ($share < $min ? '— too rare' : '— too common') }}</span>
                    </div>
                    <div class="mt-1 h-2.5 rounded-full bg-paper-2"><div @class(['h-full rounded-full', 'bg-flag-green' => $ok, 'bg-flag-red' => ! $ok]) style="width: {{ min(100, $share * 4) }}%"></div></div>
                </li>
            @endforeach
        </ul>
        <p class="mt-4 text-sm text-ink-2">Displayed match %: min {{ $report['match']['min'] }}, max {{ $report['match']['max'] }}, average {{ $report['match']['avg'] }}.</p>
    </div>
@endif
<p class="mt-4 max-w-2xl text-sm text-ink-2">To fix an imbalance, nudge the place's trait profile (Locations) or an answer's location bonus (Questions), then reload this page.</p>
@endsection
