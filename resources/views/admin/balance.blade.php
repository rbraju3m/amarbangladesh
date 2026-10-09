@extends('layouts.admin')
@section('title', 'Balance')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Result balance</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">
        Every possible answer combination ({{ number_format($combinations) }}) is played through the current scoring.
        Each place should win between {{ $min }}% and {{ $max }}% of them — otherwise some results almost never appear, or appear too often.
        Real players lean toward flattering answers, so the thin bar repeats the check with the most {{ $favouredTrait }} answer in each question picked {{ $favouredP }}% of the time.
    </p>
</header>

@if ($tooMany)
    <p class="rounded-2xl bg-flag-red/10 p-4 text-sm text-flag-red">Too many combinations to simulate here. Use <code>php artisan quiz:simulate</code>.</p>
@else
    @php
        $scale = 25; // bar width: 25% of combinations fills the track
        $inRange = fn ($v) => $v >= $min && $v <= $max;
        $out = collect($report['share'])->keys()->filter(fn ($slug) => ! $inRange($report['share'][$slug]) || ! $inRange($favoured['share'][$slug]));
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
                    @php($ok = $inRange($share))
                    @php($fav = $favoured['share'][$slug])
                    @php($favOk = $inRange($fav))
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
                        <div class="mt-1 flex items-center gap-2">
                            <div class="relative h-1.5 flex-1 rounded-full bg-paper-2">
                                <div @class(['h-full rounded-full', 'bg-ink-2/60' => $favOk, 'bg-flag-red' => ! $favOk]) style="width: {{ min(100, $fav / $scale * 100) }}%"></div>
                            </div>
                            <span @class(['shrink-0 text-right text-xs whitespace-nowrap tabular-nums', 'text-ink-2' => $favOk, 'font-semibold text-flag-red' => ! $favOk])>{{ $favouredTrait }}-leaning {{ $fav }}%</span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex items-center gap-2 text-xs text-ink-2"><i class="inline-block h-3 w-6 rounded-full bg-flag-green/15 ring-1 ring-flag-green/30"></i> healthy band {{ $min }}–{{ $max }}% · track ends at {{ $scale }}% · thin bar: {{ $favouredTrait }}-leaning players</div>
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
                Nudge the place's trait profile in <a href="{{ route('admin.locations.index') }}" class="font-semibold text-ink underline">Places</a>, or an answer's place bonus in <a href="{{ route('admin.questions.index') }}" class="font-semibold text-ink underline">Questions</a>, then come back here.
            </section>
        </aside>
    </div>
@endif

{{-- Real players: their stored answers re-scored with the current content --}}
<section class="panel mt-4">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold">Real players, replayed</h2>
        @if ($replay['replayed'] > 0)
            @if ($replay['replayed'] < $trusted)
                <span class="pill bg-paper-2 text-ink-2">Only {{ number_format($replay['replayed']) }} {{ \Illuminate\Support\Str::plural('player', $replay['replayed']) }} · too few to trust yet</span>
            @else
                <span class="pill bg-flag-green/10 text-green-text">{{ number_format($replay['replayed']) }} players</span>
            @endif
        @endif
    </div>
    <p class="max-w-2xl text-sm text-ink-2">
        Each player's latest answers, scored again with the current weights and profiles. Unlike the simulation above, this uses the answers people really pick.
        Edit a place or question, then come back to see how real players would move.
    </p>

    @if ($replay['replayed'] === 0)
        <p class="mt-4 rounded-xl bg-paper-2 p-4 text-sm text-ink-2">No plays to replay yet{{ $replay['skipped'] ? ' (older plays no longer fit the current questions)' : '' }}.</p>
    @else
        @php($scale = max(25, ceil(max([...array_values($replay['then']), ...array_values($replay['now'])]) / 5) * 5))
        <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_18rem]">
            <ul class="space-y-3">
                @foreach ($replay['now'] as $slug => $now)
                    @php($then = $replay['then'][$slug] ?? 0)
                    {{-- Only flag out-of-range shares once there are enough players for them to mean something --}}
                    @php($flag = $replay['replayed'] >= $trusted && ($now < $min || $now > $max))
                    <li>
                        <div class="flex justify-between text-sm font-semibold">
                            <span>{{ $locations[$slug]->emoji ?? '' }} {{ $locations[$slug]->name_bn ?? $slug }}</span>
                            <span class="tabular-nums text-ink-2">{{ $then }}% → <b @class(['text-flag-red' => $flag, 'text-ink' => ! $flag])>{{ $now }}%</b></span>
                        </div>
                        <div class="mt-1.5 space-y-1">
                            <div class="h-1.5 rounded-full bg-paper-2"><div class="h-full rounded-full" style="width: {{ min(100, $then / $scale * 100) }}%; background: var(--viz-1)"></div></div>
                            <div class="h-1.5 rounded-full bg-paper-2"><div class="h-full rounded-full" style="width: {{ min(100, $now / $scale * 100) }}%; background: var(--viz-2)"></div></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <aside class="space-y-3 text-sm">
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-2">
                    <span class="flex items-center gap-1.5"><i class="inline-block h-1.5 w-4 rounded-full" style="background: var(--viz-1)"></i>What they got</span>
                    <span class="flex items-center gap-1.5"><i class="inline-block h-1.5 w-4 rounded-full" style="background: var(--viz-2)"></i>With current content</span>
                </div>
                <div class="rounded-xl bg-paper-2 p-3">
                    <div class="text-xs text-ink-2">Would get a different place now</div>
                    <div class="text-xl font-bold tabular-nums">{{ number_format($replay['changed']) }} <span class="text-sm font-normal text-ink-2">of {{ number_format($replay['replayed']) }}</span></div>
                </div>
                @if ($replay['favoured_rate'] !== null)
                    <div class="rounded-xl bg-paper-2 p-3">
                        <div class="text-xs text-ink-2">Picked the most {{ $favouredTrait }} answer</div>
                        <div class="text-xl font-bold tabular-nums">{{ $replay['favoured_rate'] }}%</div>
                        <div class="text-xs text-ink-2">of the time · the check above assumes {{ $favouredP }}%, random picking would be {{ $replay['random_rate'] }}%</div>
                    </div>
                @endif
                @if ($replay['skipped'])
                    <p class="text-xs text-ink-2">{{ number_format($replay['skipped']) }} older {{ \Illuminate\Support\Str::plural('play', $replay['skipped']) }} skipped: their answers no longer fit the active questions.</p>
                @endif
                <p class="text-xs text-ink-2">One play per person (their latest), up to the {{ number_format(\App\Quiz\Replay::LIMIT) }} most recent. Bars end at {{ $scale }}%.</p>
            </aside>
        </div>
    @endif
</section>
@endsection
