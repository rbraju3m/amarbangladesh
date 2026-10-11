@extends('layouts.admin')
@section('title', 'Helplines')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Helplines</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">The public list at <a href="{{ lroute('helplines') }}" class="underline" target="_blank">/helplines</a>, also linked from help posts, the Ask form and the footer (the links show once at least one number is on). A number can be turned on only after it has been checked: call it or confirm it on an official site, then press “Checked today”. Numbers can't be deleted: turn a wrong one off. Every change is in the <a href="{{ route('admin.community.log') }}" class="underline">action log</a>.</p>
</header>

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

<div class="panel !p-0 overflow-x-auto">
    <table class="w-full min-w-[48rem] text-sm">
        <thead class="text-left text-xs font-semibold text-ink-2 uppercase">
            <tr class="border-b border-line">
                <th class="px-3 py-2.5 font-semibold">Number</th>
                <th class="px-3 py-2.5 font-semibold">Name</th>
                <th class="px-3 py-2.5 font-semibold">Kind · area</th>
                <th class="px-3 py-2.5 font-semibold">Last checked</th>
                <th class="px-3 py-2.5 font-semibold">State</th>
                <th class="px-3 py-2.5"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($helplines as $h)
                <tr id="helpline-{{ $h->id }}" @class(['border-b border-line last:border-0 align-top', 'text-ink-2' => ! $h->is_active])>
                    <td class="px-3 py-2.5 font-bold whitespace-nowrap tabular-nums">{{ $h->number }}</td>
                    <td class="max-w-xs px-3 py-2.5">
                        <p lang="bn">{{ $h->name_bn }}</p>
                        @if ($h->name_en)<p class="text-xs text-ink-2">{{ $h->name_en }}</p>@endif
                    </td>
                    <td class="px-3 py-2.5 text-xs">{{ \App\Models\Helpline::KINDS[$h->kind][1] ?? '' }} {{ str_replace('_', ' & ', ucfirst($h->kind)) }}<br><span class="text-ink-2">{{ $h->area?->name_en ?? 'Whole country' }}</span></td>
                    <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                        @if ($h->last_checked_at){{ $h->last_checked_at->format('j M Y') }}@else<span class="pill bg-warn/10 text-warn">Not checked</span>@endif
                        @if ($h->source)<p class="mt-0.5 max-w-[12rem] truncate text-ink-2" title="{{ $h->source }}">{{ $h->source }}</p>@endif
                    </td>
                    <td class="px-3 py-2.5">
                        <span @class(['pill', 'bg-flag-green/10 text-green-text' => $h->is_active, 'bg-paper-2 text-ink' => ! $h->is_active])>{{ $h->is_active ? 'On' : 'Off' }}</span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex flex-wrap justify-end gap-2">
                            <a href="{{ route('admin.helplines.edit', $h) }}" class="btn-outline !min-h-9 text-xs">Edit</a>
                            @unless ($h->last_checked_at?->isToday())
                                <form method="POST" action="{{ route('admin.helplines.update', $h) }}">@csrf @method('PUT')
                                    <input type="hidden" name="checked" value="1">
                                    <button class="btn-outline !min-h-9 text-xs">Checked today</button>
                                </form>
                            @endunless
                            @if ($h->is_active || $h->last_checked_at)
                                <form method="POST" action="{{ route('admin.helplines.update', $h) }}">@csrf @method('PUT')
                                    <input type="hidden" name="toggle" value="1"><input type="hidden" name="is_active" value="{{ $h->is_active ? 0 : 1 }}">
                                    <button class="btn-outline !min-h-9 text-xs">{{ $h->is_active ? 'Turn off' : 'Turn on' }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-ink-2">No helplines yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<form method="POST" action="{{ route('admin.helplines.store') }}" class="panel mt-6 max-w-3xl">
    @csrf
    <h2 class="mb-3 font-bold">Add a helpline</h2>
    @include('admin.helplines.fields')
    <button class="btn mt-4">Add helpline</button>
</form>
@endsection
