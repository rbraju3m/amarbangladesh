@extends('layouts.admin')
@section('title', 'Edit '.$location->name_en)
@section('content')
<a href="{{ route('admin.locations.index') }}" class="text-sm text-ink-2">← Locations</a>
<form method="POST" action="{{ route('admin.locations.update', $location) }}" class="mt-3 grid gap-6 lg:grid-cols-[1fr_20rem]">
    @csrf @method('PUT')
    <div class="stat grid gap-4 sm:grid-cols-2">
        <label class="text-sm font-medium">Name (Bangla)<input name="name_bn" value="{{ old('name_bn', $location->name_bn) }}" required class="input mt-1"></label>
        <label class="text-sm font-medium">Name (English)<input name="name_en" value="{{ old('name_en', $location->name_en) }}" required class="input mt-1"></label>
        <label class="text-sm font-medium">Emoji<input name="emoji" value="{{ old('emoji', $location->emoji) }}" required class="input mt-1"></label>
        <label class="text-sm font-medium">Personality title<input name="title_bn" value="{{ old('title_bn', $location->title_bn) }}" required class="input mt-1"></label>
        <label class="text-sm font-medium sm:col-span-2">Tagline<input name="tagline_bn" value="{{ old('tagline_bn', $location->tagline_bn) }}" required class="input mt-1"></label>
        <label class="text-sm font-medium sm:col-span-2">Description<textarea name="description_bn" rows="4" required class="input mt-1">{{ old('description_bn', $location->description_bn) }}</textarea></label>
        <label class="text-sm font-medium sm:col-span-2">"Why" ending <span class="text-ink-2">(after the player's two answers, e.g. "… — এই combination সিলেট ছাড়া আর কোথায় মেলে?")</span><input name="reason_tail_bn" value="{{ old('reason_tail_bn', $location->reason_tail_bn) }}" required class="input mt-1"></label>
        <label class="text-sm font-medium">Badges <span class="text-ink-2">(one per line, 3–4)</span><textarea name="badges" rows="4" required class="input mt-1">{{ old('badges', implode("\n", $location->badges ?? [])) }}</textarea></label>
        <div class="space-y-4">
            <label class="block text-sm font-medium">Accent colour<input name="accent_color" value="{{ old('accent_color', $location->accent_color) }}" required pattern="#[0-9a-fA-F]{6}" class="input mt-1"></label>
            <label class="block text-sm font-medium">Sort order<input type="number" name="sort_order" value="{{ old('sort_order', $location->sort_order) }}" class="input mt-1"></label>
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active))> Active (can be a result)</label>
        </div>
        <label class="text-sm font-medium">Illustration path<input name="illustration" value="{{ old('illustration', $location->illustration) }}" placeholder="images/locations/{{ $location->slug }}.svg" class="input mt-1"></label>
        <label class="text-sm font-medium">Preview image path<input name="og_image" value="{{ old('og_image', $location->og_image) }}" placeholder="images/og/{{ $location->slug }}.png" class="input mt-1"></label>
    </div>

    <div class="space-y-4">
        <div class="stat">
            <h2 class="font-bold">Trait profile</h2>
            <p class="mb-3 text-xs text-ink-2">0–10. Players whose answers have the same <em>shape</em> as this profile get this place.</p>
            <div class="space-y-2">
                @foreach ($traits as $t)
                    <label class="flex items-center justify-between text-sm">{{ $t->emoji }} {{ $t->label_bn }}
                        <input type="number" min="0" max="10" name="profile[{{ $t->key }}]" value="{{ old('profile.'.$t->key, $location->profile[$t->key] ?? 0) }}" class="num"></label>
                @endforeach
            </div>
        </div>
        <img src="{{ $location->illustrationUrl() }}" alt="" class="w-full rounded-2xl">
        <button class="btn-primary">Save</button>
        <a href="{{ route('admin.balance') }}" class="block text-center text-sm underline">Check balance →</a>
    </div>
</form>
@endsection
