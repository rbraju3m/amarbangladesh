@extends('layouts.admin')
@section('title', 'Locations')
@section('content')
<h1 class="mb-5 text-2xl font-bold">Locations</h1>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($locations as $l)
        <a href="{{ route('admin.locations.edit', $l) }}" @class(['stat flex gap-3 hover:border-accent', 'opacity-50' => ! $l->is_active])>
            <img src="{{ $l->illustrationUrl() }}" alt="" class="size-16 rounded-xl object-cover">
            <div class="min-w-0">
                <div class="font-bold">{{ $l->emoji }} {{ $l->name_bn }} <span class="text-xs font-normal text-ink-2">{{ $l->name_en }}</span></div>
                <div class="truncate text-sm">{{ $l->title_bn }}</div>
                <div class="text-xs text-ink-2">{{ number_format($l->results_count) }} results {{ $l->is_active ? '' : '· disabled' }}</div>
            </div>
        </a>
    @endforeach
</div>
<p class="mt-4 text-sm text-ink-2">Adding a new place also needs an illustration, a preview image and a map position, so it is done in code (database/seeders/data/quiz.php) rather than here.</p>
@endsection
