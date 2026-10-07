{{-- One answer card in the question editor. $i is the form index ('__INDEX__' in the add-answer template). --}}
@php
    $weights = array_filter($o['weights'] ?? []);
    $bonus = array_filter($o['bonus'] ?? []);
    $isNew = empty($o['id']);
@endphp
<details data-answer class="group rounded-2xl border border-line bg-card shadow-[0_1px_2px_rgb(20_33_27/0.04)] open:border-ink-2/30" @if ($open ?? false) open @endif>
    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
        <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-paper-2 text-xs font-bold text-ink-2">{{ is_int($i) ? $i + 1 : '+' }}</span>
        <span data-summary class="min-w-0 flex-1 truncate font-semibold">{{ ($o['emoji'] ?? '') ?: '•' }}&nbsp; {{ ($o['label_bn'] ?? '') ?: 'New answer' }}</span>
        <span class="hidden flex-wrap justify-end gap-1 sm:flex">
            @foreach ($traits->filter(fn ($t) => isset($weights[$t->key])) as $t)
                <span @class(['pill', 'bg-accent/10 text-accent' => $weights[$t->key] > 0, 'bg-flag-red/10 text-flag-red' => $weights[$t->key] < 0])>{{ $t->emoji }} {{ $weights[$t->key] > 0 ? '+' : '' }}{{ $weights[$t->key] }}</span>
            @endforeach
            @foreach ($locations->filter(fn ($l) => isset($bonus[$l->slug])) as $l)
                <span class="pill bg-[#eda100]/15 text-[#8a5a00]">{{ $l->emoji }} bonus {{ $bonus[$l->slug] }}</span>
            @endforeach
        </span>
        @if (! ($o['is_active'] ?? true))<span class="pill bg-paper-2 text-ink-2">off</span>@endif
        <svg class="size-4 shrink-0 text-ink-2 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </summary>

    <div class="space-y-5 border-t border-line px-4 pt-4 pb-5">
        <input type="hidden" name="options[{{ $i }}][id]" value="{{ $o['id'] ?? '' }}">
        <div class="grid gap-3 sm:grid-cols-[5rem_1fr_1fr]">
            <label><span class="field-label">Emoji</span><input data-emoji name="options[{{ $i }}][emoji]" value="{{ $o['emoji'] ?? '' }}" class="input text-center text-lg"></label>
            <label><span class="field-label">Label</span><input data-label name="options[{{ $i }}][label_bn]" value="{{ $o['label_bn'] ?? '' }}" placeholder="যেমন: পাহাড়ে" class="input text-base"></label>
            <label><span class="field-label">"Why" phrase</span><input name="options[{{ $i }}][reason_bn]" value="{{ $o['reason_bn'] ?? '' }}" placeholder="used in the result text" class="input"></label>
        </div>
        <label class="block max-w-xl"><span class="field-label">Image path <span class="normal-case">(image questions only)</span></span><input name="options[{{ $i }}][image]" value="{{ $o['image'] ?? '' }}" placeholder="images/locations/…svg" class="input"></label>

        <div>
            <p class="field-label">Trait points <span class="normal-case">−5 … +10</span></p>
            <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                @foreach ($traits as $t)
                    <label class="grid grid-cols-[8rem_1fr_2.5rem] items-center gap-3 text-sm">
                        <span class="truncate">{{ $t->emoji }} {{ $t->label_bn }}</span>
                        <input type="range" data-slider min="-5" max="10" step="1" name="options[{{ $i }}][weights][{{ $t->key }}]" value="{{ $o['weights'][$t->key] ?? 0 }}" class="slider" aria-label="{{ $t->label_bn }}">
                        <output class="slider-value">{{ ($o['weights'][$t->key] ?? 0) > 0 ? '+' : '' }}{{ $o['weights'][$t->key] ?? 0 }}</output>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <p class="field-label">Place bonus <span class="normal-case">0 … 5, for signature answers only (ইলিশ → বরিশাল)</span></p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($locations as $l)
                    <label class="flex items-center justify-between gap-2 rounded-xl border border-line px-2.5 py-1.5 text-sm">
                        <span class="truncate">{{ $l->emoji }} {{ $l->name_bn }}</span>
                        <input type="number" min="0" max="5" placeholder="0" name="options[{{ $i }}][bonus][{{ $l->slug }}]" value="{{ $o['bonus'][$l->slug] ?? '' }}" class="num bonus-input !w-12">
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-5 text-sm">
            <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="options[{{ $i }}][is_active]" value="1" @checked($o['is_active'] ?? true) class="size-4 accent-accent"> Active</label>
            @unless ($isNew)<label class="flex items-center gap-2 font-medium text-flag-red"><input type="checkbox" name="options[{{ $i }}][_delete]" value="1" class="size-4 accent-[var(--red)]"> Delete this answer on save</label>@endunless
        </div>
    </div>
</details>
