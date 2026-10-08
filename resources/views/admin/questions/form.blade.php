@extends('layouts.admin')
@section('title', $question->exists ? 'Edit question' : 'New question')
@section('content')
@php
    $options = array_values(old('options', $question->options->map(fn ($o) => [
        'id' => $o->id, 'label_bn' => $o->label_bn, 'label_en' => $o->label_en, 'emoji' => $o->emoji, 'image' => $o->image, 'reason_bn' => $o->reason_bn, 'reason_en' => $o->reason_en,
        'weights' => $o->trait_weights, 'bonus' => $o->location_bonus, 'is_active' => $o->is_active,
    ])->all()));
    // A new question starts with the 4 answers every question needs.
    if (! $options) {
        $options = array_fill(0, 4, ['is_active' => true]);
    }
@endphp

<a href="{{ route('admin.questions.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-2 hover:text-ink">← All questions</a>

<form method="POST" action="{{ $question->exists ? route('admin.questions.update', $question) : route('admin.questions.store') }}" class="mt-3" data-dirty-guard>
    @csrf
    @if ($question->exists) @method('PUT') @endif

    <header class="mb-5">
        <h1 class="text-2xl font-bold md:text-3xl">{{ $question->exists ? 'Edit question' : 'New question' }}</h1>
        <p class="mt-1 text-sm text-ink-2">Changes apply to new plays only; results already shared keep their outcome.</p>
    </header>

    <div class="xl:grid xl:grid-cols-[minmax(0,1fr)_17rem] xl:items-start xl:gap-6">
    <div class="min-w-0">
    <section class="panel grid gap-4 md:grid-cols-[2fr_1.3fr_9rem]">
        <label><span class="field-label">Question (Bangla)</span><input name="prompt_bn" value="{{ old('prompt_bn', $question->prompt_bn) }}" required class="input text-base"></label>
        <label><span class="field-label">Subtitle <span class="normal-case">(optional)</span></span><input name="subtitle_bn" value="{{ old('subtitle_bn', $question->subtitle_bn) }}" class="input"></label>
        <label><span class="field-label">Question (English) <span class="normal-case">shown on /en; empty = Bangla</span></span><input name="prompt_en" value="{{ old('prompt_en', $question->prompt_en) }}" lang="en" class="input text-base"></label>
        <label><span class="field-label">Subtitle (English) <span class="normal-case">(optional)</span></span><input name="subtitle_en" value="{{ old('subtitle_en', $question->subtitle_en) }}" lang="en" class="input"></label>
        <label><span class="field-label">Kind</span>
            <select name="kind" class="input">
                @foreach (\App\Models\Question::KINDS as $kind)<option value="{{ $kind }}" @selected(old('kind', $question->kind) === $kind)>{{ ucfirst($kind) }}</option>@endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm font-semibold md:col-span-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $question->exists ? $question->is_active : true)) class="size-4 accent-accent"> Active — shown to players</label>
    </section>

    <section class="mt-6">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold">Answers</h2>
                <p class="text-sm text-ink-2"><b>Trait points</b> move the player toward places with that trait. <b>Place bonus</b> is a direct nudge (each point ≈ {{ \App\Quiz\Scorer::BONUS_WEIGHT }} similarity) — use sparingly.</p>
            </div>
        </div>
        <div id="answers" class="space-y-3" data-next="{{ count($options) }}">
            @foreach ($options as $i => $o)
                @include('admin.partials.answer', ['i' => $i, 'o' => $o, 'open' => empty($o['id']) || $errors->any()])
            @endforeach
        </div>
        <button type="button" class="btn-outline mt-3 w-full border-dashed" data-add-answer="#answers" data-max="6">+ Add answer</button>
        <template id="answer-template">@include('admin.partials.answer', ['i' => '__INDEX__', 'o' => ['is_active' => true], 'open' => true])</template>
    </section>
    </div>

    {{-- Phone preview, drawn by admin.js from the form (hidden without JS). Beside the form on wide screens, collapsible above the answers otherwise. --}}
    <details data-q-preview hidden class="group mt-6 xl:sticky xl:top-6 xl:order-last xl:mt-0" open>
        <summary class="cursor-pointer list-none text-sm font-semibold text-ink-2 xl:pointer-events-none [&::-webkit-details-marker]:hidden"><span class="xl:hidden">▸ </span>Phone preview <span class="font-normal">· live</span></summary>
        <div class="mt-3 flex justify-center">
            <div class="flex h-[32rem] w-64 flex-col overflow-hidden rounded-[2.2rem] border-[7px] border-ink bg-paper px-3 pt-4 pb-3 text-center shadow-xl" data-q-phone></div>
        </div>
    </details>
    </div>

    <div class="mt-6">@include('admin.partials.balance-preview')</div>

    {{-- Sticky save bar --}}
    <div class="sticky bottom-0 z-10 -mx-4 mt-8 border-t border-line bg-paper/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
        <div class="flex flex-wrap items-center gap-3">
            <button class="btn !bg-flag-red !text-white">Save question</button>
            <a href="{{ route('admin.balance') }}" class="btn-outline">Check balance</a>
            <span data-balance-chip hidden></span>
            <span data-dirty-hint hidden class="pill bg-[#eda100]/15 text-[#8a5a00]">● Unsaved changes</span>
        </div>
    </div>
</form>

@if ($question->exists)
    <section class="mt-10 rounded-2xl border border-flag-red/30 p-5">
        <h2 class="font-bold text-flag-red">Danger zone</h2>
        <p class="mt-1 text-sm text-ink-2">Deleting removes the question and its answers. Turning off "Active" is usually better — it keeps the history.</p>
        <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" class="mt-3" onsubmit="return confirm('Delete this question and its answers?')">
            @csrf @method('DELETE')
            <button class="btn-outline !border-flag-red/40 !text-flag-red">Delete question</button>
        </form>
    </section>
@endif
@endsection
