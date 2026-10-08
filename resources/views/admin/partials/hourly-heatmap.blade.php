{{-- Plays per hour of the day (Asia/Dhaka): one row per date, or per weekday for long ranges. Darker = more plays. --}}
@php
    $rows = $hourly['rows'];
    $peak = max(1, ...array_merge(...array_map(fn ($r) => $r['plays'], $rows ?: [['plays' => [0]]])));
    $hourName = fn ($h) => \Carbon\Carbon::createFromTime($h)->format('g a');
    $rowName = fn ($key) => $hourly['by'] === 'weekday'
        ? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][(int) $key]
        : \Carbon\Carbon::parse($key)->format('D j M');
    $busiest = max($hourly['hours']) > 0 ? array_search(max($hourly['hours']), $hourly['hours']) : null;
@endphp
<div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
    @if ($busiest !== null)
        <span>Busiest hour <b>{{ $hourName($busiest) }} – {{ $hourName(($busiest + 1) % 24) }}</b> <span class="text-ink-2">· {{ number_format($hourly['hours'][$busiest]) }} {{ \Illuminate\Support\Str::plural('play', $hourly['hours'][$busiest]) }}</span></span>
    @endif
    <span class="flex items-center gap-1.5 text-xs text-ink-2">fewer
        @foreach ([0.15, 0.4, 0.7, 1] as $o)<i class="inline-block size-3 rounded-sm" style="background: color-mix(in srgb, var(--viz-1) {{ $o * 100 }}%, transparent)"></i>@endforeach
        more plays</span>
    <span class="ml-auto text-xs text-ink-2">{{ $hourly['by'] === 'weekday' ? 'by weekday' : 'by date' }} · Bangladesh time · hover a cell for visitors</span>
</div>

<div class="-mx-5 mt-3 max-h-[28rem] overflow-auto px-5">
    <table class="w-full min-w-[640px] table-fixed border-separate border-spacing-[3px] text-[10px] tabular-nums">
        <thead class="sticky top-0 z-[1] bg-card">
            <tr>
                <th class="w-24"></th>
                @foreach (range(0, 23) as $h)
                    <th class="overflow-visible text-left font-normal whitespace-nowrap text-ink-2">{{ $h % 3 === 0 ? \Carbon\Carbon::createFromTime($h)->format('ga') : '' }}</th>
                @endforeach
                <th class="w-12 pl-2 text-right font-semibold text-ink-2">Plays</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <th class="pr-2 text-left text-xs font-normal whitespace-nowrap text-ink-2">{{ $rowName($r['key']) }}</th>
                    @foreach ($r['plays'] as $h => $n)
                        <td class="h-6 rounded-sm text-center {{ $n / $peak > 0.55 ? 'font-bold text-white' : 'text-ink' }}"
                            style="background: {{ $n ? 'color-mix(in srgb, var(--viz-1) '.round(15 + 85 * $n / $peak).'%, transparent)' : 'var(--paper-2)' }}"
                            title="{{ $rowName($r['key']) }}, {{ $hourName($h) }} – {{ $hourName(($h + 1) % 24) }}: {{ $n }} {{ \Illuminate\Support\Str::plural('play', $n) }}, {{ $r['visitors'][$h] }} {{ \Illuminate\Support\Str::plural('visitor', $r['visitors'][$h]) }}">{{ $n ?: '' }}</td>
                    @endforeach
                    <td class="pl-2 text-right text-xs font-semibold">{{ number_format(array_sum($r['plays'])) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
