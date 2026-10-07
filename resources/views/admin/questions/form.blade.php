@extends('layouts.admin')
@section('title', $question->exists ? 'Edit question' : 'New question')
@section('content')
@php
    $options = old('options', $question->options->map(fn ($o) => [
        'id' => $o->id, 'label_bn' => $o->label_bn, 'emoji' => $o->emoji, 'image' => $o->image, 'reason_bn' => $o->reason_bn,
        'weights' => $o->trait_weights, 'bonus' => $o->location_bonus, 'is_active' => $o->is_active,
    ])->all());
    $options = array_merge(array_values($options), array_fill(0, max(0, 4 - count($options)) + 1, ['is_active' => true]));
@endphp
<a href="{{ route('admin.questions.index') }}" class="text-sm text-ink-2">← Questions</a>
<form method="POST" action="{{ $question->exists ? route('admin.questions.update', $question) : route('admin.questions.store') }}" class="mt-3 space-y-6">
    @csrf
    @if ($question->exists) @method('PUT') @endif

    <section class="stat grid gap-4 md:grid-cols-[1fr_1fr_auto_auto]">
        <label class="text-sm font-medium">Question (Bangla)<input name="prompt_bn" value="{{ old('prompt_bn', $question->prompt_bn) }}" required class="input mt-1 text-base"></label>
        <label class="text-sm font-medium">Subtitle (optional)<input name="subtitle_bn" value="{{ old('subtitle_bn', $question->subtitle_bn) }}" class="input mt-1"></label>
        <label class="text-sm font-medium">Kind
            <select name="kind" class="input mt-1">
                @foreach (\App\Models\Question::KINDS as $kind)<option value="{{ $kind }}" @selected(old('kind', $question->kind) === $kind)>{{ $kind }}</option>@endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $question->is_active))> Active</label>
    </section>

    <section>
        <h2 class="font-bold">Answers</h2>
        <p class="mb-3 text-sm text-ink-2">
            <b>Trait points</b> (−5…10) move the player toward places with that trait. <b>Place bonus</b> (0…5) is a direct nudge for a signature answer (ইলিশ → বরিশাল); each point ≈ {{ \App\Quiz\Scorer::BONUS_WEIGHT }} similarity. Use sparingly.
            Leave a blank row empty to ignore it.
        </p>
        <div class="space-y-3">
            @foreach ($options as $i => $o)
                <fieldset class="stat">
                    <input type="hidden" name="options[{{ $i }}][id]" value="{{ $o['id'] ?? '' }}">
                    <div class="grid gap-3 md:grid-cols-[5rem_1fr_1fr_1fr]">
                        <label class="text-xs font-medium">Emoji<input name="options[{{ $i }}][emoji]" value="{{ $o['emoji'] ?? '' }}" class="input mt-1 text-center text-lg"></label>
                        <label class="text-xs font-medium">Label<input name="options[{{ $i }}][label_bn]" value="{{ $o['label_bn'] ?? '' }}" class="input mt-1"></label>
                        <label class="text-xs font-medium">"Why" phrase <span class="text-ink-2">(used in result text)</span><input name="options[{{ $i }}][reason_bn]" value="{{ $o['reason_bn'] ?? '' }}" class="input mt-1"></label>
                        <label class="text-xs font-medium">Image path <span class="text-ink-2">(image questions)</span><input name="options[{{ $i }}][image]" value="{{ $o['image'] ?? '' }}" placeholder="images/locations/…svg" class="input mt-1"></label>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                        <span class="w-full text-xs font-semibold text-ink-2">Trait points</span>
                        @foreach ($traits as $t)
                            <label class="flex items-center gap-1 text-xs">{{ $t->emoji }} {{ $t->label_bn }}<input type="number" min="-5" max="10" name="options[{{ $i }}][weights][{{ $t->key }}]" value="{{ $o['weights'][$t->key] ?? '' }}" class="num"></label>
                        @endforeach
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                        <span class="w-full text-xs font-semibold text-ink-2">Place bonus</span>
                        @foreach ($locations as $l)
                            <label class="flex items-center gap-1 text-xs">{{ $l->emoji }} {{ $l->name_bn }}<input type="number" min="0" max="5" name="options[{{ $i }}][bonus][{{ $l->slug }}]" value="{{ $o['bonus'][$l->slug] ?? '' }}" class="num"></label>
                        @endforeach
                    </div>
                    <div class="mt-3 flex gap-4 text-xs">
                        <label class="flex items-center gap-1.5"><input type="checkbox" name="options[{{ $i }}][is_active]" value="1" @checked($o['is_active'] ?? true)> Active</label>
                        @if (! empty($o['id']))<label class="flex items-center gap-1.5 text-flag-red"><input type="checkbox" name="options[{{ $i }}][_delete]" value="1"> Delete this answer</label>@endif
                    </div>
                </fieldset>
            @endforeach
        </div>
    </section>

    <div class="flex items-center gap-3">
        <button class="btn-primary !w-auto">Save</button>
        <a href="{{ route('admin.balance') }}" class="text-sm underline">Check balance →</a>
    </div>
</form>

@if ($question->exists)
    <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" class="mt-10" onsubmit="return confirm('Delete this question and its answers? Disabling it is usually better.')">
        @csrf @method('DELETE')
        <button class="text-sm text-flag-red underline">Delete question</button>
    </form>
@endif
@endsection
