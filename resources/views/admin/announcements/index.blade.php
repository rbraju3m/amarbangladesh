@extends('layouts.admin')
@section('title', 'Announcements')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Announcements</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">A short bar under the header on every public page, quiz and community, in the page's language. Announcements can't be deleted: turn one off, or give it an end time. Every change is in the <a href="{{ route('admin.community.log') }}" class="underline">action log</a>.</p>
</header>

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

<section class="panel mb-6" aria-labelledby="now-title">
    <h2 id="now-title" class="font-bold">Showing now</h2>
    @if ($current)
        <div class="mt-3 overflow-hidden rounded-xl border border-line">@include('partials.announcement', ['announcement' => $current, 'preview' => true])</div>
    @else
        <p class="mt-1 text-sm text-ink-2">Nothing: the bar is hidden.</p>
    @endif
</section>

@if ($announcements->isNotEmpty())
    <div class="panel !p-0 overflow-x-auto">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="text-left text-xs font-semibold text-ink-2 uppercase">
                <tr class="border-b border-line">
                    <th class="px-3 py-2.5 font-semibold">Text</th>
                    <th class="px-3 py-2.5 font-semibold">When (Dhaka)</th>
                    <th class="px-3 py-2.5 font-semibold">State</th>
                    <th class="px-3 py-2.5"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($announcements as $a)
                    @php($state = $a->state())
                    <tr id="announcement-{{ $a->id }}" @class(['border-b border-line last:border-0 align-top', 'text-ink-2' => $state !== 'live'])>
                        <td class="max-w-md px-3 py-2.5">
                            <p class="line-clamp-2" lang="bn">@if ($a->level === 'warning')<span class="pill mr-1 bg-warn/10 text-warn">Warning</span>@endif{{ $a->text_bn }}</p>
                            @if ($a->text_en)<p class="mt-0.5 line-clamp-1 text-xs text-ink-2">{{ $a->text_en }}</p>@endif
                            @if ($a->link)<p class="mt-0.5 truncate text-xs text-ink-2">→ {{ $a->link }}</p>@endif
                        </td>
                        <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                            {{ $a->starts_at?->format('j M Y, H:i') ?? 'From '.$a->created_at->format('j M Y, H:i') }}<br>
                            {{ $a->ends_at ? 'to '.$a->ends_at->format('j M Y, H:i') : 'no end' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span @class(['pill', 'bg-flag-green/10 text-green-text' => $state === 'live', 'bg-paper-2 text-ink' => $state !== 'live'])>{{ ['live' => 'Live', 'scheduled' => 'Scheduled', 'ended' => 'Ended', 'off' => 'Off'][$state] }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.announcements.edit', $a) }}" class="btn-outline !min-h-9 text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.announcements.update', $a) }}">@csrf @method('PUT')
                                    <input type="hidden" name="toggle" value="1"><input type="hidden" name="is_active" value="{{ $a->is_active ? 0 : 1 }}">
                                    <button class="btn-outline !min-h-9 text-xs">{{ $a->is_active ? 'Turn off' : 'Turn on' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<form method="POST" action="{{ route('admin.announcements.store') }}" class="panel mt-6 max-w-3xl">
    @csrf
    <h2 class="mb-3 font-bold">Add an announcement</h2>
    @include('admin.announcements.fields')
    <button class="btn mt-4">Add announcement</button>
</form>
@endsection
