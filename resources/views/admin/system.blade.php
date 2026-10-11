@extends('layouts.admin')
@section('title', 'System')
@section('content')
@php($size = fn (?int $b) => $b === null ? '—' : ($b >= 1024 ** 3 ? number_format($b / 1024 ** 3, 1).' GB' : ($b >= 1024 ** 2 ? number_format($b / 1024 ** 2, 1).' MB' : number_format($b / 1024).' KB')))
@php($ago = fn ($t) => $t ? $t->copy()->locale('en')->diffForHumans(['parts' => 1]) : 'never')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">System</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">Is the server doing its background work, are emails going out, is there room left. Counted live at {{ $checkedAt->format('H:i:s') }}; reload to check again. Run times are kept in the cache, so clearing it resets them until the next run.</p>
</header>

<section class="panel mb-6" aria-labelledby="checks-title">
    <h2 id="checks-title" class="font-bold">Checks</h2>
    <ul class="mt-3 divide-y divide-line">
        @foreach ($checks as [$label, $ok, $problem])
            <li class="flex items-start gap-3 py-2.5 text-sm">
                <span @class(['pill shrink-0', 'bg-flag-green/10 text-green-text' => $ok, 'bg-danger/10 text-danger' => ! $ok])>{{ $ok ? 'OK' : 'Check' }}</span>
                <div><p class="font-semibold">{{ $label }}</p>@unless ($ok)<p class="text-ink-2">{{ $problem }}</p>@endunless</div>
            </li>
        @endforeach
    </ul>
</section>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="panel" aria-labelledby="jobs-title">
        <h2 id="jobs-title" class="font-bold">Background jobs</h2>
        <table class="mt-3 w-full text-sm">
            <thead class="text-left text-xs font-semibold text-ink-2 uppercase"><tr class="border-b border-line"><th class="py-2 pr-3 font-semibold">Job</th><th class="hidden py-2 pr-3 font-semibold sm:table-cell">Should run</th><th class="py-2 font-semibold">Last run</th></tr></thead>
            <tbody>
                @foreach ($jobs as $name => $job)
                    <tr class="border-b border-line last:border-0 align-top">
                        @php($every = 'every '.($job['every'] >= 1440 ? 'day' : ($job['every'] >= 60 ? 'hour' : ($job['every'] === 1 ? 'minute' : $job['every'].' min'))))
                        <td class="py-2 pr-3"><p class="font-semibold">{{ $job['label'] }}</p><p class="text-xs break-all text-ink-2">{{ $name }}<span class="sm:hidden"> · {{ $every }}</span></p></td>
                        <td class="hidden py-2 pr-3 text-xs whitespace-nowrap sm:table-cell">{{ $every }}</td>
                        <td class="py-2">
                            <span @class(['pill', 'bg-flag-green/10 text-green-text' => $job['ok'], 'bg-danger/10 text-danger' => ! $job['ok']])>{{ $ago($job['at']) }}</span>
                            @if ($job['at'])<p class="mt-0.5 text-xs text-ink-2">{{ $job['at']->format('j M, H:i') }}@foreach ($job['meta'] as $k => $v) · {{ $k }} {{ $v }}@endforeach</p>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="panel" aria-labelledby="mail-title">
        <h2 id="mail-title" class="font-bold">Email and queue</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-xs text-ink-2">Sent, 24 h</dt><dd class="text-2xl font-bold tabular-nums">{{ number_format($mail['sent']) }}</dd></div>
            <div><dt class="text-xs text-ink-2">Failed, 24 h</dt><dd @class(['text-2xl font-bold tabular-nums', 'text-danger' => $mail['failed']])>{{ number_format($mail['failed']) }}</dd></div>
            <div><dt class="text-xs text-ink-2">Mailer</dt><dd class="font-semibold">{{ $mail['driver'] }}</dd></div>
            <div><dt class="text-xs text-ink-2">Queue</dt><dd class="font-semibold">{{ $queue['driver'] }}</dd></div>
            <div><dt class="text-xs text-ink-2">Jobs waiting</dt><dd class="text-2xl font-bold tabular-nums">{{ $queue['waiting'] ?? '—' }}</dd></div>
            <div><dt class="text-xs text-ink-2">Failed jobs</dt><dd @class(['text-2xl font-bold tabular-nums', 'text-danger' => $queue['failed']])>{{ $queue['failed'] ?? '—' }}</dd></div>
        </dl>
        @if ($mail['lastError'])<p class="mt-3 text-xs text-ink-2">Last failure {{ \Illuminate\Support\Carbon::parse($mail['lastError']['at'])->format('j M, H:i') }}: {{ $mail['lastError']['error'] }} (details in the log).</p>@endif
        <p class="mt-3 text-xs text-ink-2">Notification emails are sent by the scheduler, not a queue worker, so “jobs waiting” normally stays 0. Mail with the <code>log</code> mailer is written to the log, not sent.</p>
    </section>

    <section class="panel" aria-labelledby="storage-title">
        <h2 id="storage-title" class="font-bold">Database and disk</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-ink-2">Database ({{ $database['count'] }} tables)</dt><dd class="text-2xl font-bold tabular-nums">{{ $size($database['bytes']) }}</dd></div>
            <div><dt class="text-xs text-ink-2">Disk free</dt><dd class="text-2xl font-bold tabular-nums">{{ $size($disk['free']) }}</dd>@if ($disk['total'])<dd class="text-xs text-ink-2">of {{ $size($disk['total']) }}</dd>@endif</div>
        </dl>
        @if ($database['top'])
            <table class="mt-4 w-full text-sm">
                <thead class="text-left text-xs font-semibold text-ink-2 uppercase"><tr class="border-b border-line"><th class="py-1.5 font-semibold">Biggest tables</th><th class="py-1.5 text-right font-semibold">Rows (approx.)</th><th class="py-1.5 text-right font-semibold">Size</th></tr></thead>
                <tbody>
                    @foreach ($database['top'] as $t)
                        <tr class="border-b border-line last:border-0"><td class="py-1.5">{{ $t->name }}</td><td class="py-1.5 text-right tabular-nums">{{ number_format((int) $t->row_count) }}</td><td class="py-1.5 text-right tabular-nums">{{ $size((int) $t->bytes) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="panel" aria-labelledby="versions-title">
        <h2 id="versions-title" class="font-bold">Versions and settings</h2>
        <dl class="mt-3 divide-y divide-line text-sm">
            @foreach ($versions as $k => $v)
                <div class="flex justify-between gap-3 py-1.5"><dt class="text-ink-2">{{ $k }}</dt><dd class="font-semibold">{{ $v ?? '—' }}</dd></div>
            @endforeach
        </dl>
    </section>
</div>
@endsection
