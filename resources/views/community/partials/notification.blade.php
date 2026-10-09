{{-- One notification. Anonymous answers name no one (the same rule as the public page). --}}
@php
    $answer = $notification->answer;
    $who = $answer->publicAuthor()?->displayName() ?? __('একজন (বেনামী)');
    [$emoji, $line] = match ($notification->type) {
        'accepted' => ['✓', __('আপনার উত্তরটি সমাধান হিসেবে বেছে নেওয়া হয়েছে')],
        'need' => ['🙏', __('আপনিও যেটা জানতে চেয়েছিলেন, তার উত্তর দিয়েছেন :name', ['name' => $who])],
        default => ['💬', __(':name আপনার পোস্টে উত্তর দিয়েছেন', ['name' => $who])],
    };
@endphp
<a href="{{ $notification->post->url() }}#answer-{{ $answer->id }}" @click="track('notification_clicked', { meta: { type: '{{ $notification->type }}' } })"
    @class(['notice-card', 'is-unread' => ! $notification->read_at])>
    <span class="notice-icon" aria-hidden="true">{{ $emoji }}</span>
    <span class="min-w-0 flex-1">
        <span class="block font-semibold">{{ $line }}</span>
        <span class="mt-0.5 block truncate text-sm">“{{ $notification->post->title }}”</span>
        <span class="mt-1 line-clamp-2 block text-sm text-ink-2">{{ \Illuminate\Support\Str::limit($answer->body, 160) }}</span>
        <time datetime="{{ $notification->created_at->toIso8601String() }}" class="mt-1 block text-xs text-ink-2">{{ \App\Support\Lang::ago($notification->created_at) }}</time>
    </span>
    @unless ($notification->read_at)
        <span class="sr-only">{{ __('নতুন') }}</span>
        <span class="mt-2 size-2.5 shrink-0 rounded-full bg-flag-red" aria-hidden="true"></span>
    @endunless
</a>
