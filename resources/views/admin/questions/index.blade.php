@extends('layouts.admin')
@section('title', 'Questions')
@section('content')
<div class="mb-5 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Questions</h1>
    <a href="{{ route('admin.questions.create') }}" class="btn-ghost">+ New question</a>
</div>
<ol class="space-y-2">
    @foreach ($questions as $q)
        <li @class(['stat flex items-center gap-3', 'opacity-50' => ! $q->is_active])>
            <span class="w-6 text-center font-bold text-ink-2">{{ $loop->iteration }}</span>
            <div class="min-w-0 flex-1">
                <a href="{{ route('admin.questions.edit', $q) }}" class="font-semibold hover:underline">{{ $q->prompt_bn }}</a>
                <div class="text-xs text-ink-2">{{ $q->options_count }} answers · {{ $q->kind }} {{ $q->is_active ? '' : '· disabled' }}</div>
            </div>
            <form method="POST" action="{{ route('admin.questions.move', [$q, 'up']) }}">@csrf<button class="num" aria-label="Move up" @disabled($loop->first)>↑</button></form>
            <form method="POST" action="{{ route('admin.questions.move', [$q, 'down']) }}">@csrf<button class="num" aria-label="Move down" @disabled($loop->last)>↓</button></form>
        </li>
    @endforeach
</ol>
@endsection
