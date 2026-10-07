@extends('layouts.admin')
@section('title', 'Locations')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Locations</h1>
    <p class="mt-1 text-sm text-ink-2">The {{ $locations->count() }} places a player can get. Edit the text, colour and trait profile of each.</p>
</header>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($locations as $l)
        <a href="{{ route('admin.locations.edit', $l) }}" @class(['group panel overflow-hidden !p-0 transition hover:-translate-y-0.5 hover:shadow-lg', 'opacity-60' => ! $l->is_active])>
            <div class="relative aspect-[16/9] overflow-hidden" style="background: {{ $l->accent_color }}">
                <img src="{{ $l->illustrationUrl() }}" alt="" class="size-full object-cover transition duration-300 group-hover:scale-105">
                <span class="pill absolute top-3 right-3 bg-card/90 text-ink shadow-sm">{{ number_format($l->results_count) }} results</span>
                @unless ($l->is_active)<span class="pill absolute top-3 left-3 bg-flag-red text-white">Off</span>@endunless
            </div>
            <div class="flex items-center gap-3 p-4">
                <span class="size-3 shrink-0 rounded-full" style="background: {{ $l->accent_color }}"></span>
                <div class="min-w-0">
                    <div class="font-bold">{{ $l->emoji }} {{ $l->name_bn }} <span class="text-xs font-medium text-ink-2">{{ $l->name_en }}</span></div>
                    <div class="truncate text-sm text-ink-2">{{ $l->title_bn }}</div>
                </div>
            </div>
        </a>
    @endforeach
</div>
<p class="mt-6 text-sm text-ink-2">Adding a new place also needs an illustration, a preview image and a map position, so it is done in code (<code>database/seeders/data/quiz.php</code>).</p>
@endsection
