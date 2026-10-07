@extends('layouts.admin')
@section('title', 'Questions')
@section('content')
<header class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold md:text-3xl">Questions</h1>
        <p class="mt-1 text-sm text-ink-2">{{ $questions->where('is_active', true)->count() }} of {{ $questions->count() }} active · players see them in this order.</p>
    </div>
    <a href="{{ route('admin.questions.create') }}" class="btn">+ New question</a>
</header>

<p class="mb-3 hidden text-xs text-ink-2" data-sortable-hint>Drag <span aria-hidden="true">⠿</span> to reorder (or focus it and use ↑/↓). The new order is saved straight away.</p>
<ol class="space-y-2" data-sortable="{{ route('admin.questions.reorder') }}" data-csrf="{{ csrf_token() }}">
    @foreach ($questions as $q)
        <li data-id="{{ $q->id }}" @class(['panel flex items-center gap-3 !p-3 transition hover:border-ink-2/40 md:gap-4 md:!p-4', 'opacity-60' => ! $q->is_active])>
            <button type="button" data-handle hidden class="-mx-1 grid h-9 w-6 shrink-0 cursor-grab touch-none place-items-center rounded-md text-lg text-ink-2 hover:bg-paper-2 hover:text-ink active:cursor-grabbing" aria-label="Drag to reorder: {{ $q->prompt_bn }}">⠿</button>
            <span data-num class="grid size-9 shrink-0 place-items-center rounded-xl bg-paper-2 font-bold text-ink-2 tabular-nums">{{ $loop->iteration }}</span>
            <a href="{{ route('admin.questions.edit', $q) }}" class="min-w-0 flex-1">
                <span class="block truncate font-semibold md:text-lg">{{ $q->prompt_bn }}</span>
                <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-2">
                    <span class="text-base leading-none tracking-wider">{{ $q->options->pluck('emoji')->filter()->implode(' ') }}</span>
                    <span class="pill bg-paper-2">{{ $q->options_count }} {{ \Illuminate\Support\Str::plural('answer', $q->options_count) }}</span>
                    <span class="pill bg-paper-2">{{ ucfirst($q->kind) }}</span>
                    @unless ($q->is_active)<span class="pill bg-flag-red/10 text-flag-red">Off</span>@endunless
                </span>
            </a>
            <div class="flex shrink-0 gap-1">
                <form method="POST" action="{{ route('admin.questions.move', [$q, 'up']) }}">@csrf<button data-up class="grid size-9 place-items-center rounded-lg border border-line text-ink-2 transition hover:border-ink-2 hover:text-ink disabled:opacity-30" aria-label="Move up" @disabled($loop->first)>↑</button></form>
                <form method="POST" action="{{ route('admin.questions.move', [$q, 'down']) }}">@csrf<button data-down class="grid size-9 place-items-center rounded-lg border border-line text-ink-2 transition hover:border-ink-2 hover:text-ink disabled:opacity-30" aria-label="Move down" @disabled($loop->last)>↓</button></form>
                <a href="{{ route('admin.questions.edit', $q) }}" class="hidden h-9 items-center rounded-lg border border-line px-3 text-sm font-semibold transition hover:border-ink-2 sm:flex">Edit</a>
            </div>
        </li>
    @endforeach
</ol>
@endsection
