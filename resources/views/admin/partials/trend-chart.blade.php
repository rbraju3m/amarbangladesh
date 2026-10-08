{{-- Line chart of visitors / completed / shared per day, drawn server-side as SVG; admin.js adds the hover tooltip. --}}
@php
    $series = [
        ['key' => 'visitors', 'label' => 'Visitors', 'color' => 'var(--viz-1)'],
        ['key' => 'completed', 'label' => 'Completed', 'color' => 'var(--viz-3)'],
        ['key' => 'shared', 'label' => 'Shared', 'color' => 'var(--viz-2)', 'dash' => '6 5'],
    ];
    [$W, $H, $left, $right, $top, $bottom] = [800, 250, 36, 676, 14, 214];
    $n = count($rows);
    $peak = max(1, ...array_map(fn ($r) => max($r['visitors'], $r['completed'], $r['shared']), $rows ?: [['visitors' => 0, 'completed' => 0, 'shared' => 0]]));
    // Round the axis up to 1, 2 or 5 × 10^k.
    $mag = 10 ** floor(log10($peak));
    $yMax = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($v) => $v >= $peak);
    $x = fn ($i) => $n > 1 ? $left + $i * ($right - $left) / ($n - 1) : ($left + $right) / 2;
    $y = fn ($v) => $bottom - ($v / $yMax) * ($bottom - $top);
    $weekly = $n > 1 && \Carbon\Carbon::parse($rows[1]['date'])->diffInDays(\Carbon\Carbon::parse($rows[0]['date'])) >= 7;
    $label = fn ($d) => \Carbon\Carbon::parse($d)->format('j M');
    $every = max(1, (int) ceil($n / 7));

    // End labels, nudged apart so they never overlap.
    $ends = collect($series)->map(fn ($s) => [...$s, 'value' => $rows[$n - 1][$s['key']] ?? 0, 'y' => $y($rows[$n - 1][$s['key']] ?? 0)])->sortBy('y')->values()->all();
    for ($i = 1; $i < count($ends); $i++) {
        $ends[$i]['ty'] = max($ends[$i]['y'], ($ends[$i - 1]['ty'] ?? $ends[$i - 1]['y']) + 15);
    }
    $ends[0]['ty'] = $ends[0]['y'];

    $data = array_map(fn ($r) => [...$r, 'label' => ($weekly ? 'Week of ' : '').\Carbon\Carbon::parse($r['date'])->format('D j M')], $rows);
@endphp
<div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
    @foreach ($series as $s)
        <span data-series="{{ $s['key'] }}" data-label="{{ $s['label'] }}" data-color="{{ $s['color'] }}" class="flex items-center gap-2">
            <svg viewBox="0 0 16 4" class="h-1 w-4" aria-hidden="true"><line x1="1" x2="15" y1="2" y2="2" stroke="{{ $s['color'] }}" stroke-width="3" stroke-linecap="round" @isset($s['dash']) stroke-dasharray="3 4" @endisset /></svg>{{ $s['label'] }}
            <b class="tabular-nums">{{ number_format(array_sum(array_column($rows, $s['key']))) }}</b>
        </span>
    @endforeach
    <span class="ml-auto text-xs text-ink-2">{{ $weekly ? 'per week' : 'per day' }} · people, not page views</span>
</div>

<div class="-mx-5 mt-3 overflow-x-auto px-5">
<div class="relative min-w-[640px]" data-trend='@json($data)' data-plot='@json(['left' => $left, 'right' => $right])'>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="block h-auto w-full select-none" tabindex="0" role="img" aria-label="Daily visitors, completions and shares">
        @foreach ([0, $yMax / 2, $yMax] as $tick)
            <line x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="var(--line)" stroke-width="1" @if ($tick > 0) stroke-dasharray="3 5" @endif />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="11" fill="var(--ink-2)">{{ $tick == (int) $tick ? number_format($tick) : $tick }}</text>
        @endforeach
        @foreach ($rows as $i => $r)
            @if ($i % $every === 0 || $i === $n - 1)
                <text x="{{ $x($i) }}" y="{{ $bottom + 22 }}" text-anchor="middle" font-size="11" fill="var(--ink-2)">{{ $label($r['date']) }}</text>
            @endif
        @endforeach
        <line data-cross x1="0" x2="0" y1="{{ $top }}" y2="{{ $bottom }}" stroke="var(--ink-2)" stroke-width="1" style="opacity: 0" />
        @foreach ($series as $s)
            <polyline fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" @isset($s['dash']) stroke-dasharray="{{ $s['dash'] }}" @endisset
                points="{{ collect($rows)->map(fn ($r, $i) => round($x($i), 1).','.round($y($r[$s['key']]), 1))->implode(' ') }}" />
        @endforeach
        @foreach ($ends as $e)
            <circle cx="{{ $x($n - 1) }}" cy="{{ $e['y'] }}" r="4" fill="{{ $e['color'] }}" stroke="var(--card)" stroke-width="2" />
            <text x="{{ $right + 10 }}" y="{{ $e['ty'] + 4 }}" font-size="12" font-weight="700" fill="var(--ink)">{{ number_format($e['value']) }} <tspan font-weight="500" fill="var(--ink-2)">{{ strtolower($e['label']) }}</tspan></text>
        @endforeach
    </svg>
    <div data-tip hidden class="pointer-events-none absolute top-0 z-10 min-w-40 rounded-xl border border-line bg-card px-3 py-2 text-xs shadow-lg"></div>
</div>
</div>

<details class="mt-3 text-sm">
    <summary class="cursor-pointer text-xs font-semibold text-ink-2">Show as table</summary>
    <div class="mt-2 max-h-64 overflow-auto rounded-xl border border-line">
        <table class="w-full text-left text-xs tabular-nums">
            <thead class="sticky top-0 bg-paper-2"><tr><th class="px-3 py-2">{{ $weekly ? 'Week of' : 'Day' }}</th>@foreach ($series as $s)<th class="px-3 py-2 text-right">{{ $s['label'] }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-line">
                @foreach (array_reverse($data) as $r)
                    <tr><td class="px-3 py-1.5">{{ $label($r['date']) }}</td>@foreach ($series as $s)<td class="px-3 py-1.5 text-right">{{ number_format($r[$s['key']]) }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
