{{-- Fields shared by "add" (index) and "edit". Times are Dhaka time. --}}
@php($a = $announcement ?? null)
<label class="block"><span class="field-label">Text (Bangla)</span><textarea name="text_bn" required maxlength="240" rows="2" class="input" placeholder="ঈদের ছুটিতে উত্তর আসতে একটু দেরি হতে পারে।">{{ old('text_bn', $a?->text_bn) }}</textarea></label>
<label class="mt-3 block"><span class="field-label">Text (English) <span class="normal-case">optional · English pages show the Bangla without it</span></span><textarea name="text_en" maxlength="240" rows="2" class="input">{{ old('text_en', $a?->text_en) }}</textarea></label>
<div class="mt-3 grid gap-3 sm:grid-cols-[1fr_10rem]">
    <label class="block"><span class="field-label">Link <span class="normal-case">optional · /feed?category=… or https://…</span></span><input name="link" value="{{ old('link', $a?->link) }}" maxlength="500" class="input" placeholder="/feed?category=health"></label>
    <label class="block"><span class="field-label">Look</span>
        <select name="level" class="input">
            <option value="info" @selected(old('level', $a?->level ?? 'info') === 'info')>Info</option>
            <option value="warning" @selected(old('level', $a?->level) === 'warning')>Warning</option>
        </select>
    </label>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2">
    <label class="block"><span class="field-label">Starts <span class="normal-case">empty = now</span></span><input type="datetime-local" name="starts_at" value="{{ old('starts_at', $a?->starts_at?->format('Y-m-d\TH:i')) }}" class="input"></label>
    <label class="block"><span class="field-label">Ends <span class="normal-case">empty = until turned off</span></span><input type="datetime-local" name="ends_at" value="{{ old('ends_at', $a?->ends_at?->format('Y-m-d\TH:i')) }}" class="input"></label>
</div>
<input type="hidden" name="is_active" value="0">
<label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $a?->is_active ?? true)) class="size-5 accent-[var(--green)]"> Switched on</label>
<p class="mt-2 text-xs text-ink-2">Times are Dhaka time. Only one shows at a time: a warning before info, then the one that started last. People can close it with ✕ (remembered on their device); a new announcement shows again. Cached pages pick up a change within about 10 minutes.</p>
