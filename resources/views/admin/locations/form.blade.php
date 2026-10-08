@extends('layouts.admin')
@section('title', 'Edit '.$location->name_en)
@section('content')
<a href="{{ route('admin.locations.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-ink-2 hover:text-ink">← All locations</a>

<form method="POST" action="{{ route('admin.locations.update', $location) }}" class="mt-3" data-dirty-guard>
    @csrf @method('PUT')

    <header class="mb-5 flex items-center gap-4">
        <span class="grid size-14 shrink-0 place-items-center rounded-2xl text-3xl" style="background: {{ $location->accent_color }}22">{{ $location->emoji }}</span>
        <div>
            <h1 class="text-2xl font-bold md:text-3xl">{{ $location->name_bn }} <span class="text-lg font-medium text-ink-2">{{ $location->name_en }}</span></h1>
            <p class="text-sm text-ink-2">Shared results keep their old text; new plays use these values.</p>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="panel grid gap-4 sm:grid-cols-2">
                <h2 class="font-bold sm:col-span-2">Names &amp; text</h2>
                <label><span class="field-label">Name (Bangla)</span><input name="name_bn" value="{{ old('name_bn', $location->name_bn) }}" required class="input"></label>
                <label><span class="field-label">Name (English)</span><input name="name_en" value="{{ old('name_en', $location->name_en) }}" required class="input"></label>
                <label><span class="field-label">Emoji</span><input name="emoji" value="{{ old('emoji', $location->emoji) }}" required class="input"></label>
                <label><span class="field-label">Personality title</span><input name="title_bn" value="{{ old('title_bn', $location->title_bn) }}" required class="input"></label>
                <label class="sm:col-span-2"><span class="field-label">Tagline</span><input name="tagline_bn" value="{{ old('tagline_bn', $location->tagline_bn) }}" required class="input"></label>
                <label class="sm:col-span-2"><span class="field-label">Description</span><textarea name="description_bn" rows="4" required class="input">{{ old('description_bn', $location->description_bn) }}</textarea></label>
                <label class="sm:col-span-2"><span class="field-label">"Why" ending</span><input name="reason_tail_bn" value="{{ old('reason_tail_bn', $location->reason_tail_bn) }}" required class="input">
                    <span class="mt-1 block text-xs text-ink-2">Follows the player's two answers, e.g. "… — এমন মিশেল সিলেট ছাড়া আর কোথায় মেলে?"</span></label>
                <details class="rounded-xl border border-line p-3 sm:col-span-2" @if ($location->title_en) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold">English <span class="font-medium text-ink-2">— shown on /en; empty fields fall back to Bangla</span></summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2" lang="en">
                        <label><span class="field-label">Personality title</span><input name="title_en" value="{{ old('title_en', $location->title_en) }}" class="input"></label>
                        <label class="sm:col-span-2"><span class="field-label">Tagline</span><input name="tagline_en" value="{{ old('tagline_en', $location->tagline_en) }}" class="input"></label>
                        <label class="sm:col-span-2"><span class="field-label">Description</span><textarea name="description_en" rows="4" class="input">{{ old('description_en', $location->description_en) }}</textarea></label>
                        <label class="sm:col-span-2"><span class="field-label">"Why" ending</span><input name="reason_tail_en" value="{{ old('reason_tail_en', $location->reason_tail_en) }}" class="input"></label>
                        <label class="sm:col-span-2"><span class="field-label">Badges <span class="normal-case">one per line</span></span><textarea name="badges_en" rows="4" class="input">{{ old('badges_en', implode("\n", $location->badges_en ?? [])) }}</textarea></label>
                    </div>
                </details>
                <label class="sm:col-span-2"><span class="field-label">Badges <span class="normal-case">one per line, 3–4</span></span><textarea name="badges" rows="4" required class="input">{{ old('badges', implode("\n", $location->badges ?? [])) }}</textarea></label>
            </section>

            <section class="panel grid gap-4 sm:grid-cols-2">
                <h2 class="font-bold sm:col-span-2">Look &amp; settings</h2>
                <label><span class="field-label">Accent colour</span>
                    <span class="flex gap-2" data-color-pair>
                        <input type="color" value="{{ old('accent_color', $location->accent_color) }}" class="h-11 w-14 shrink-0 cursor-pointer rounded-xl border border-line bg-card p-1" aria-label="Pick colour">
                        <input name="accent_color" value="{{ old('accent_color', $location->accent_color) }}" required pattern="#[0-9a-fA-F]{6}" class="input font-mono">
                    </span>
                </label>
                <label><span class="field-label">Sort order</span><input type="number" name="sort_order" value="{{ old('sort_order', $location->sort_order) }}" class="input"></label>
                <label><span class="field-label">Illustration path</span><input name="illustration" value="{{ old('illustration', $location->illustration) }}" placeholder="images/locations/{{ $location->slug }}.svg" class="input"></label>
                <label><span class="field-label">Preview image path</span><input name="og_image" value="{{ old('og_image', $location->og_image) }}" placeholder="images/og/{{ $location->slug }}.png" class="input"></label>
                <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active)) class="size-4 accent-accent"> Active — can be a result</label>
            </section>
        </div>

        <aside class="space-y-4">
            {{-- Result preview: server-rendered, kept live by admin.js (data-bind). --}}
            <div class="panel overflow-hidden !p-0" data-loc-preview style="--accent: {{ $location->accent_color }}">
                <p class="flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-ink-2"><span>Result preview</span><span class="pill bg-paper-2">as players see it</span></p>
                <div class="relative aspect-[4/3]" style="background: var(--accent)">
                    <img data-bind-img data-default="{{ $location->illustrationUrl() }}" src="{{ $location->illustrationUrl() }}" alt="" class="size-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/5 to-transparent"></div>
                    <div class="absolute inset-x-4 bottom-3 text-white">
                        <p class="text-xs opacity-85">তোমার বাংলাদেশ</p>
                        <p class="text-3xl leading-tight font-bold"><span data-bind="emoji">{{ $location->emoji }}</span> <span data-bind="name_bn">{{ $location->name_bn }}</span></p>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-lg leading-snug font-bold" style="color: var(--accent)" data-bind="title_bn">{{ $location->title_bn }}</p>
                    <p class="mt-0.5 text-sm text-ink-2" data-bind="tagline_bn">{{ $location->tagline_bn }}</p>
                    <div class="mt-3 flex flex-wrap gap-1.5" data-bind-badges>
                        @foreach ($location->badges ?? [] as $badge)<span class="chip !py-1 text-xs">{{ $badge }}</span>@endforeach
                    </div>
                </div>
            </div>
            <section class="panel">
                <h2 class="font-bold">Trait profile</h2>
                <p class="mb-4 text-xs text-ink-2">0–10. Players whose answers have the same <em>shape</em> as this profile get this place.</p>
                <div class="space-y-3">
                    @foreach ($traits as $t)
                        <label class="grid grid-cols-[7rem_1fr_2rem] items-center gap-3 text-sm">
                            <span class="truncate">{{ $t->emoji }} {{ $t->label_bn }}</span>
                            <input type="range" data-slider min="0" max="10" step="1" name="profile[{{ $t->key }}]" value="{{ old('profile.'.$t->key, $location->profile[$t->key] ?? 0) }}" class="slider" aria-label="{{ $t->label_bn }}">
                            <output class="slider-value">{{ old('profile.'.$t->key, $location->profile[$t->key] ?? 0) }}</output>
                        </label>
                    @endforeach
                </div>
            </section>
            @include('admin.partials.balance-preview')
        </aside>
    </div>

    <div class="sticky bottom-0 z-10 -mx-4 mt-8 border-t border-line bg-paper/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
        <div class="flex flex-wrap items-center gap-3">
            <button class="btn !bg-flag-red !text-white">Save location</button>
            <a href="{{ route('admin.balance') }}" class="btn-outline">Check balance</a>
            <span data-balance-chip hidden></span>
            <span data-dirty-hint hidden class="pill bg-[#eda100]/15 text-[#8a5a00]">● Unsaved changes</span>
        </div>
    </div>
</form>
@endsection
