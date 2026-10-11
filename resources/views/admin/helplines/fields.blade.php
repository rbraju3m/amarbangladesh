{{-- Fields shared by "add" (index) and "edit". --}}
@php($h = $helpline ?? null)
@php($areaSlug = old('area', $h?->area_id ? collect(\App\Community\Taxonomy::areas())->firstWhere('id', $h->area_id)['slug'] ?? '' : ''))
<div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
    <label class="block"><span class="field-label">Number</span><input name="number" value="{{ old('number', $h?->number) }}" required maxlength="20" inputmode="tel" class="input tabular-nums" placeholder="999"></label>
    <label class="block"><span class="field-label">Kind</span>
        <select name="kind" class="input">
            @foreach (\App\Models\Helpline::KINDS as $key => [$label, $emoji])
                <option value="{{ $key }}" @selected(old('kind', $h?->kind ?? 'emergency') === $key)>{{ $emoji }} {{ str_replace('_', ' & ', ucfirst($key)) }}</option>
            @endforeach
        </select>
    </label>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2">
    <label class="block"><span class="field-label">Name (Bangla)</span><input name="name_bn" value="{{ old('name_bn', $h?->name_bn) }}" required maxlength="120" class="input" placeholder="জাতীয় জরুরি সেবা"></label>
    <label class="block"><span class="field-label">Name (English) <span class="normal-case">optional</span></span><input name="name_en" value="{{ old('name_en', $h?->name_en) }}" maxlength="120" class="input"></label>
    <label class="block"><span class="field-label">Note (Bangla) <span class="normal-case">optional · one short line</span></span><input name="note_bn" value="{{ old('note_bn', $h?->note_bn) }}" maxlength="200" class="input" placeholder="পুলিশ, ফায়ার সার্ভিস, অ্যাম্বুলেন্স"></label>
    <label class="block"><span class="field-label">Note (English) <span class="normal-case">optional</span></span><input name="note_en" value="{{ old('note_en', $h?->note_en) }}" maxlength="200" class="input"></label>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-[1fr_8rem]">
    <label class="block"><span class="field-label">Area <span class="normal-case">empty = whole country</span></span>
        <select name="area" class="input">
            <option value="">Whole country</option>
            @include('admin.partials.area-options', ['selected' => $areaSlug])
        </select>
    </label>
    <label class="block"><span class="field-label">Order</span><input type="number" name="sort_order" value="{{ old('sort_order', $h?->sort_order) }}" min="0" max="65000" class="input" placeholder="last"></label>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-[12rem_1fr]">
    <label class="block"><span class="field-label">Last checked</span><input type="date" name="last_checked_at" value="{{ old('last_checked_at', $h?->last_checked_at?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" class="input"></label>
    <label class="block"><span class="field-label">Source <span class="normal-case">how it was checked · admins only</span></span><input name="source" value="{{ old('source', $h?->source) }}" maxlength="300" class="input" placeholder="Called it on 11 Oct · or https://…"></label>
</div>
<input type="hidden" name="is_active" value="0">
<label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $h?->is_active ?? false)) class="size-5 accent-[var(--green)]"> Switched on (needs a “last checked” date)</label>
<p class="mt-2 text-xs text-ink-2">The public page shows the number, name, note, area and the “last checked” date, never the source. Cached pages pick up a change within about 10 minutes.</p>
