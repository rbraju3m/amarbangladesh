@extends('layouts.admin')
@section('title', 'Plays')
@section('content')
@php($fmt = fn ($n) => number_format($n))
<header class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold md:text-3xl">All plays</h1>
        <p class="mt-1 text-sm text-ink-2">Every saved result, newest first. Times are Bangladesh time; tap a play to open its shared page.</p>
    </div>
    <nav class="flex flex-wrap gap-1 rounded-xl border border-line bg-card p-1 text-sm font-semibold" aria-label="Date range">
        @foreach ($ranges as $key => $label)
            <a href="{{ route('admin.plays', array_filter(['days' => $key, 'place' => $place, 'source' => $source])) }}" @class(['rounded-lg px-3 py-1.5 transition', 'bg-ink text-paper' => $days == $key, 'text-ink-2 hover:text-ink' => $days != $key]) @if ($days == $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</header>

<form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3">
    <input type="hidden" name="days" value="{{ $days }}">
    <label class="min-w-40 flex-1">
        <span class="field-label">Place</span>
        <select name="place" class="input" onchange="this.form.submit()">
            <option value="">All places</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}" @selected($place === $loc->id)>{{ $loc->emoji }} {{ $loc->name_bn }}</option>
            @endforeach
        </select>
    </label>
    <label class="min-w-40 flex-1">
        <span class="field-label">Came from</span>
        <select name="source" class="input" onchange="this.form.submit()">
            <option value="">Anywhere</option>
            <option value="direct" @selected($source === 'direct')>Landing page</option>
            <option value="friend" @selected($source === 'friend')>A friend's link</option>
        </select>
    </label>
    <noscript><button class="btn-outline">Filter</button></noscript>
    @if ($place || $source)
        <a href="{{ route('admin.plays', ['days' => $days]) }}" class="btn-outline !min-h-10 text-sm">Clear filters</a>
    @endif
    <p class="ml-auto self-center text-sm text-ink-2"><b class="text-ink tabular-nums">{{ $fmt($plays->total()) }}</b> {{ \Illuminate\Support\Str::plural('play', $plays->total()) }}</p>
</form>

<section class="panel !p-0">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-xs text-ink-2">
                <tr>
                    <th class="px-4 py-3 font-semibold">Date &amp; time</th>
                    <th class="px-4 py-3 font-semibold">Result</th>
                    <th class="hidden px-4 py-3 font-semibold md:table-cell">Runner-up</th>
                    <th class="px-4 py-3 font-semibold">Name</th>
                    <th class="hidden px-4 py-3 font-semibold sm:table-cell">Came from</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($plays as $play)
                    <tr class="transition hover:bg-paper-2">
                        <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                            <time datetime="{{ $play->created_at->toIso8601String() }}" title="{{ $play->created_at->locale('en')->diffForHumans() }}">
                                <span class="block">{{ $play->created_at->format('j M Y') }}</span>
                                <span class="block text-xs text-ink-2">{{ $play->created_at->format('g:i a') }}</span>
                            </time>
                        </td>
                        <td class="px-4 py-2.5">
                            <a href="{{ route('results.show', $play->code) }}" target="_blank" rel="noopener" class="font-semibold whitespace-nowrap hover:underline">{{ $play->location?->emoji }} {{ $play->location?->name_bn ?? '?' }}</a>
                            <span class="text-ink-2 tabular-nums">· {{ $play->match_pct }}%</span>
                        </td>
                        <td class="hidden px-4 py-2.5 whitespace-nowrap text-ink-2 md:table-cell">
                            @if ($play->secondLocation){{ $play->secondLocation->emoji }} {{ $play->secondLocation->name_bn }} <span class="tabular-nums">· {{ $play->second_match_pct }}%</span>@else — @endif
                        </td>
                        <td class="max-w-40 truncate px-4 py-2.5">
                            {{ $play->display_name ?: '—' }}
                            @if ($play->referrer_result_id)<span class="block text-xs text-green-text sm:hidden">via a friend</span>@endif
                        </td>
                        <td class="hidden px-4 py-2.5 sm:table-cell">
                            @if ($play->referrer_result_id)
                                <span class="text-green-text">Friend's link</span>
                                @if ($play->referrer)
                                    <a href="{{ route('results.show', $play->referrer->code) }}" target="_blank" rel="noopener" class="block text-xs text-ink-2 hover:underline">{{ $play->referrer->display_name ?: 'no name' }}@if ($play->friend_match_pct !== null) · {{ $play->friend_match_pct }}% match @endif</a>
                                @endif
                            @else
                                <span class="text-ink-2">Landing page</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-ink-2">No plays match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if ($plays->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 text-sm" aria-label="Pages">
        @if ($plays->onFirstPage())<span class="btn-outline !min-h-10 opacity-40">← Newer</span>@else<a href="{{ $plays->previousPageUrl() }}" class="btn-outline !min-h-10">← Newer</a>@endif
        <span class="text-ink-2 tabular-nums">Page {{ $plays->currentPage() }} of {{ $plays->lastPage() }}</span>
        @if ($plays->hasMorePages())<a href="{{ $plays->nextPageUrl() }}" class="btn-outline !min-h-10">Older →</a>@else<span class="btn-outline !min-h-10 opacity-40">Older →</span>@endif
    </nav>
@endif
@endsection
