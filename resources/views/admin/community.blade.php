@extends('layouts.admin')
@section('title', 'Community')
@section('content')
@php
    $statusPill = fn ($status) => match ($status) {
        'published' => 'bg-flag-green/10 text-green-text',
        'hidden' => 'bg-viz-2/15 text-ink',
        default => 'bg-flag-red/10 text-flag-red',
    };
@endphp
<header class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold md:text-3xl">Community</h1>
        <p class="mt-1 text-sm text-ink-2">Posts and answers. Items reported by {{ \App\Community\Moderation::AUTO_HIDE_REPORTS }} different members are hidden automatically until you keep or remove them.</p>
    </div>
    <nav class="flex gap-1 rounded-xl border border-line bg-card p-1 text-sm font-semibold" aria-label="Show">
        @foreach (['attention' => 'Needs a look', 'all' => 'Everything'] as $key => $label)
            <a href="{{ route('admin.community', $key === 'all' ? ['show' => 'all'] : []) }}" @class(['rounded-lg px-3 py-1.5 transition', 'bg-ink text-paper' => $show === $key, 'text-ink-2 hover:text-ink' => $show !== $key])>{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.community.log') }}" class="rounded-lg px-3 py-1.5 text-ink-2 transition hover:text-ink">Action log →</a>
    </nav>
</header>

<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ($stats as $label => $value)
        <div class="stat"><p class="text-xs font-semibold text-ink-2">{{ $label }}</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($value) }}</p></div>
    @endforeach
</div>
<p class="-mt-3 mb-6 text-sm text-ink-2">
    Answer notification emails sent (7 days): <strong class="text-ink tabular-nums">{{ number_format($emails['sent']) }}</strong>
    · members who can get them: <strong class="text-ink tabular-nums">{{ number_format($emails['reachable']) }}</strong> of {{ number_format($emails['members']) }}
    @if (config('mail.default') === 'log')
        · <span class="font-semibold text-flag-red">MAIL_MAILER is "log": emails are only written to the log</span>
    @endif
</p>

@foreach (['post' => $posts, 'answer' => $answers] as $type => $items)
    <section class="panel mb-6 !p-0">
        <h2 class="panel-title border-b border-line px-5 py-4 !mb-0">{{ ucfirst($type) }}s <small>{{ number_format($items->total()) }}</small></h2>
        <ul class="divide-y divide-line">
            @forelse ($items as $item)
                <li class="flex flex-wrap items-start gap-3 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 text-xs text-ink-2">
                            <span class="pill {{ $statusPill($item->status) }}">{{ $item->status }}</span>
                            @if ($item->reports_count)<span class="pill bg-flag-red/10 text-flag-red">🚩 {{ $item->reports_count }} {{ \Illuminate\Support\Str::plural('report', $item->reports_count) }}</span>@endif
                            @if ($item->is_anonymous)<span class="pill bg-ink/10 text-ink" title="Shown publicly as বেনামী">🕶️ anonymous</span>@endif
                            <span>{{ $item->member->displayName() }}@if ($item->member->isBlocked()) <b class="text-flag-red">(blocked)</b>@endif</span>
                            <span>· {{ $item->created_at->format('j M Y, g:i a') }}</span>
                        </p>
                        @if ($type === 'post')
                            <p class="mt-1 font-semibold">
                                @if ($item->isPublished())<a href="{{ $item->url() }}" target="_blank" class="hover:underline">{{ $item->title }}</a>@else{{ $item->title }}@endif
                            </p>
                            @if ($item->body)<p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ $item->body }}</p>@endif
                        @else
                            <p class="mt-1 text-sm">{{ \Illuminate\Support\Str::limit($item->body, 300) }}</p>
                            <p class="mt-1 text-xs text-ink-2">on <a href="{{ route('posts.show', $item->post_id) }}" target="_blank" class="font-semibold hover:underline">{{ $item->post->title }}</a></p>
                        @endif
                        @if ($item->photos->isNotEmpty())
                            {{-- Photos are part of what's reported: open them full size to check --}}
                            <div class="mt-2 flex gap-2">
                                @foreach ($item->photos as $photo)
                                    <a href="{{ $photo->url() }}" target="_blank" class="block size-16 overflow-hidden rounded-xl border border-line bg-paper-2" title="Photo {{ $loop->iteration }} of {{ $loop->count }} (opens full size)">
                                        <img src="{{ $photo->url('thumb') }}" alt="Photo {{ $loop->iteration }}" loading="lazy" class="size-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($item->reports_count || $item->status === 'hidden')
                            <form method="POST" action="{{ route('admin.community.moderate', [$type, $item->id, 'dismiss']) }}">@csrf<button class="btn-outline !min-h-9 text-xs">Keep</button></form>
                        @elseif ($item->status !== 'published')
                            <form method="POST" action="{{ route('admin.community.moderate', [$type, $item->id, 'restore']) }}">@csrf<button class="btn-outline !min-h-9 text-xs">Restore</button></form>
                        @endif
                        @if ($item->status === 'published')
                            <form method="POST" action="{{ route('admin.community.moderate', [$type, $item->id, 'hide']) }}">@csrf<button class="btn-outline !min-h-9 text-xs">Hide</button></form>
                        @endif
                        @if ($item->status !== 'removed')
                            <form method="POST" action="{{ route('admin.community.moderate', [$type, $item->id, 'remove']) }}">@csrf<button class="btn-outline !min-h-9 text-xs text-flag-red">Remove</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.community.block', [$item->member->id, $item->member->isBlocked() ? 'unblock' : 'block']) }}">@csrf<button class="btn-outline !min-h-9 text-xs">{{ $item->member->isBlocked() ? 'Unblock author' : 'Block author' }}</button></form>
                    </div>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-ink-2">{{ $show === 'attention' ? 'Nothing reported or hidden. 🎉' : 'Nothing yet.' }}</li>
            @endforelse
        </ul>
        @if ($items->hasPages())<div class="border-t border-line px-5 py-3">{{ $items->links() }}</div>@endif
    </section>
@endforeach
@endsection
