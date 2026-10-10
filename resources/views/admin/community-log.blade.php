@extends('layouts.admin')
@section('title', 'Moderation log')
@section('content')
{{-- Read-only: every admin action and automatic hide (App\Models\AdminAction), newest first. --}}
<header class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <a href="{{ route('admin.community') }}" class="text-sm font-semibold text-ink-2 hover:text-ink">‹ Moderation</a>
        <h1 class="mt-1 text-2xl font-bold md:text-3xl">Moderation log</h1>
        <p class="mt-1 text-sm text-ink-2">Who did what, and when. Kept forever and can't be edited. "Hidden by reports" is automatic, after {{ \App\Community\Moderation::AUTO_HIDE_REPORTS }} reports.</p>
    </div>
    <form method="GET" class="flex items-center gap-2 text-sm">
        <label for="log-action" class="font-semibold text-ink-2">Show</label>
        <select id="log-action" name="action" class="field !min-h-10 !w-auto !py-1.5 !text-sm" onchange="this.form.submit()">
            <option value="">Everything</option>
            @foreach (\App\Models\AdminAction::ACTIONS as $key => [$label])
                <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <noscript><button class="btn-outline !min-h-10 text-sm">Show</button></noscript>
    </form>
</header>

<div class="overflow-hidden rounded-2xl border border-line bg-card">
    @forelse ($rows as $row)
        @php
            [$label, $pill] = \App\Models\AdminAction::ACTIONS[$row->action] ?? [$row->action, 'bg-paper-2 text-ink'];
            $target = $targets[$row->target_type][$row->target_id] ?? null;
            $meta = $row->meta ?? [];
            $url = match ($row->target_type) {
                'post' => $target ? lroute('posts.show', ['post' => $target->id]) : null,
                'answer' => $target ? lroute('posts.show', ['post' => $target->post_id]).'#answer-'.$target->id : null,
                'member' => $target && ! $target->deleted_at ? lroute('members.show', ['member' => $target->code]) : null,
                'category' => route('admin.categories.index').'#topic-'.$row->target_id,
                default => null,
            };
        @endphp
        <div class="flex flex-col gap-1.5 border-b border-line px-4 py-3 last:border-0 md:flex-row md:items-start md:gap-4">
            <div class="w-40 shrink-0 text-sm text-ink-2">
                <time datetime="{{ $row->created_at->toIso8601String() }}" title="{{ $row->created_at->format('j M Y, H:i') }}">{{ $row->created_at->format('j M, H:i') }}</time>
                <span class="block truncate text-xs">{{ $row->user?->name ?? $row->user?->email ?? ($row->user_id ? 'Deleted admin' : 'Automatic') }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="pill {{ $pill }}">{{ $label }}</span>
                    <span class="text-xs font-semibold text-ink-2 uppercase">{{ $row->target_type }} #{{ $row->target_id }}</span>
                    @if ($target && isset($target->status) && $row->target_type !== 'member')<span class="text-xs text-ink-2">now {{ $target->status }}</span>@endif
                </p>
                <p class="mt-1 truncate font-semibold">
                    @if ($url)<a href="{{ $url }}" target="_blank" class="hover:underline">{{ $meta['title'] ?? $meta['name'] ?? '—' }}</a>@else{{ $meta['title'] ?? $meta['name'] ?? '—' }}@endif
                </p>
                @if (! empty($meta['reports']))
                    <p class="mt-0.5 text-sm text-ink-2">
                        {{ $meta['reports'] }} {{ \Illuminate\Support\Str::plural('report', $meta['reports']) }}:
                        {{ collect($meta['reasons'] ?? [])->sortKeys()->map(fn ($n, $reason) => $reason.($n > 1 ? " ×{$n}" : ''))->join(', ') }}
                        @if (! empty($meta['first_report_at'])) · first report {{ \Illuminate\Support\Carbon::parse($meta['first_report_at'])->locale('en')->diffForHumans($row->created_at, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }} before @endif
                    </p>
                @endif
                @if (! empty($meta['from']))<p class="mt-0.5 text-xs text-ink-2">was {{ $meta['from'] }}</p>@endif
                @foreach ($meta['changes'] ?? [] as $field => $change)
                    <p class="mt-0.5 text-xs text-ink-2">{{ str_replace('_', ' ', $field) }}: {{ is_bool($change['from']) ? ($change['from'] ? 'on' : 'off') : ($change['from'] ?? '—') }} → {{ is_bool($change['to']) ? ($change['to'] ? 'on' : 'off') : ($change['to'] ?? '—') }}</p>
                @endforeach
            </div>
        </div>
    @empty
        <p class="px-4 py-10 text-center text-ink-2">Nothing yet. Every hide, keep, remove, restore, block and topic change will show up here.</p>
    @endforelse
</div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
