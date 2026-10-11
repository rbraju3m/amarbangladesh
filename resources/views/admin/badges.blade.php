@extends('layouts.admin')
@section('title', 'Badges')
@section('content')
<header class="mb-6">
    <h1 class="text-2xl font-bold md:text-3xl">Badges</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">A professional badge (“Doctor”, “Lawyer”…) shows next to the member's name on their posts, answers and profile, never on anonymous ones. Give one only after checking, and write down how. The note, who gave it and when stay here. Every change is in the <a href="{{ route('admin.community.log', ['action' => 'badge_give']) }}" class="underline">action log</a>.</p>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">“Phone verified” and “Email verified” need nothing here: they come from how the member signs in, and only say the contact is theirs.</p>
</header>

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

@if ($members->isNotEmpty())
    <div class="panel !p-0 overflow-x-auto">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="text-left text-xs font-semibold text-ink-2 uppercase">
                <tr class="border-b border-line">
                    <th class="px-3 py-2.5 font-semibold">Member</th>
                    <th class="px-3 py-2.5 font-semibold">Badge</th>
                    <th class="px-3 py-2.5 font-semibold">How it was checked</th>
                    <th class="px-3 py-2.5 font-semibold">Given</th>
                    <th class="px-3 py-2.5"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $m)
                    <tr id="badge-{{ $m->id }}" class="border-b border-line align-top last:border-0">
                        <td class="px-3 py-2.5">
                            <a href="{{ lroute('members.show', $m) }}" target="_blank" class="font-semibold hover:underline">{{ $m->name ?: 'Member' }}</a>
                            <span class="block text-xs text-ink-2">{{ $m->code }}</span>
                        </td>
                        <td class="px-3 py-2.5"><span lang="bn">{{ $m->badge_bn }}</span>@if ($m->badge_en)<span class="block text-xs text-ink-2">{{ $m->badge_en }}</span>@endif</td>
                        <td class="max-w-xs px-3 py-2.5 text-xs text-ink-2">{{ $m->badge_note }}</td>
                        <td class="px-3 py-2.5 text-xs whitespace-nowrap">{{ $m->badge_at?->format('j M Y, H:i') }}<span class="block text-ink-2">{{ $m->badgeGiver?->name ?? $m->badgeGiver?->email ?? 'Deleted admin' }}</span></td>
                        <td class="px-3 py-2.5">
                            <form method="POST" action="{{ route('admin.badges.destroy', $m) }}" class="flex justify-end">@csrf @method('DELETE')
                                <button class="btn-outline !min-h-9 text-xs">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="panel text-sm text-ink-2">Nobody has a badge yet.</p>
@endif

<form method="POST" action="{{ route('admin.badges.store') }}" class="panel mt-6 max-w-3xl">
    @csrf
    <h2 class="font-bold">Give or change a badge</h2>
    <p class="mt-1 text-sm text-ink-2">Giving one to a member who already has a badge replaces it.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block md:col-span-2">
            <span class="field-label">Member</span>
            <input name="member" value="{{ old('member') }}" required maxlength="300" class="input" placeholder="Profile link (…/u/abc123) or member code">
        </label>
        <label class="block">
            <span class="field-label">Badge in Bangla</span>
            <input name="badge_bn" value="{{ old('badge_bn') }}" required maxlength="40" lang="bn" class="input" placeholder="ডাক্তার">
        </label>
        <label class="block">
            <span class="field-label">Badge in English <span class="normal-case">optional</span></span>
            <input name="badge_en" value="{{ old('badge_en') }}" maxlength="40" class="input" placeholder="Doctor">
        </label>
        <label class="block md:col-span-2">
            <span class="field-label">How you checked it <span class="normal-case">admins only</span></span>
            <textarea name="badge_note" required maxlength="300" rows="2" class="input" placeholder="BMDC registration A-12345 checked online, matched on a video call">{{ old('badge_note') }}</textarea>
        </label>
    </div>
    <button class="btn mt-4">Save badge</button>
</form>
@endsection
