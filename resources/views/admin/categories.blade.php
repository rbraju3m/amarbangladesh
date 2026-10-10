@extends('layouts.admin')
@section('title', 'Topics')
@section('content')
{{-- Community topics (categories). Never deleted: turning one off hides it from Ask and the filters; its posts and old links stay. --}}
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Topics</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">What people pick when they ask, and filter by. A topic that's turned off disappears from Ask, the feed filters and the home page, but its posts keep their tag and old links still work. Topics can't be deleted. Every change is in the <a href="{{ route('admin.community.log') }}" class="underline">action log</a>.</p>
</header>

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

<div class="panel !p-0 overflow-x-auto">
    <table class="w-full min-w-[46rem] text-sm">
        <thead class="text-left text-xs font-semibold text-ink-2 uppercase">
            <tr class="border-b border-line">
                <th class="px-3 py-2.5 font-semibold">Order</th>
                <th class="px-3 py-2.5 font-semibold">Emoji</th>
                <th class="px-3 py-2.5 font-semibold">Name (Bangla)</th>
                <th class="px-3 py-2.5 font-semibold">Name (English)</th>
                <th class="px-3 py-2.5 font-semibold">Posts</th>
                <th class="px-3 py-2.5 font-semibold">On</th>
                <th class="px-3 py-2.5"><span class="sr-only">Save</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $c)
                <tr id="topic-{{ $c->id }}" @class(['border-b border-line last:border-0', 'bg-paper-2/60 text-ink-2' => ! $c->is_active])>
                    <td class="px-3 py-2"><input form="topic-form-{{ $c->id }}" name="sort_order" type="number" min="0" value="{{ $c->sort_order }}" class="input !w-16 !py-1.5" aria-label="Order of {{ $c->name_en }}"></td>
                    <td class="px-3 py-2"><input form="topic-form-{{ $c->id }}" name="emoji" value="{{ $c->emoji }}" required maxlength="16" class="input !w-14 !py-1.5 text-center" aria-label="Emoji of {{ $c->name_en }}"></td>
                    <td class="px-3 py-2"><input form="topic-form-{{ $c->id }}" name="name_bn" value="{{ $c->name_bn }}" required maxlength="60" class="input !py-1.5" aria-label="Bangla name"></td>
                    <td class="px-3 py-2">
                        <input form="topic-form-{{ $c->id }}" name="name_en" value="{{ $c->name_en }}" maxlength="60" class="input !py-1.5" aria-label="English name">
                        <a href="{{ lroute('feed', ['category' => $c->slug]) }}" target="_blank" class="mt-0.5 block text-xs text-ink-2 hover:underline">/feed?category={{ $c->slug }}</a>
                    </td>
                    <td class="px-3 py-2 tabular-nums">{{ number_format($posts[$c->id] ?? 0) }}</td>
                    <td class="px-3 py-2"><input form="topic-form-{{ $c->id }}" type="checkbox" name="is_active" value="1" @checked($c->is_active) class="size-5 accent-[var(--green)]" aria-label="{{ $c->name_en }} turned on"></td>
                    <td class="px-3 py-2 text-right">
                        <form id="topic-form-{{ $c->id }}" method="POST" action="{{ route('admin.categories.update', $c) }}">@csrf @method('PUT')<button class="btn-outline !min-h-9 text-xs">Save</button></form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<form method="POST" action="{{ route('admin.categories.store') }}" class="panel mt-6 max-w-3xl">
    @csrf
    <h2 class="font-bold">Add a topic</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-[5rem_1fr_1fr]">
        <label class="block"><span class="field-label">Emoji</span><input name="emoji" value="{{ old('emoji') }}" required maxlength="16" class="input text-center" placeholder="🌾"></label>
        <label class="block"><span class="field-label">Name (Bangla)</span><input name="name_bn" value="{{ old('name_bn') }}" required maxlength="60" class="input" placeholder="কৃষি"></label>
        <label class="block"><span class="field-label">Name (English)</span><input name="name_en" value="{{ old('name_en') }}" maxlength="60" class="input" placeholder="Farming"></label>
    </div>
    <label class="mt-3 block max-w-xs"><span class="field-label">URL name <span class="normal-case">optional · a-z, 0-9, dashes · can't change later</span></span><input name="slug" value="{{ old('slug') }}" maxlength="40" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="input" placeholder="made from the English name"></label>
    <button class="btn mt-4">Add topic</button>
    <p class="mt-2 text-xs text-ink-2">It goes at the end of the list; change its order above.</p>
</form>
@endsection
