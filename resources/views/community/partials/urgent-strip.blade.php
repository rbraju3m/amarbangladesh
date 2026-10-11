{{--
    Urgent help that hasn't run out (Feed::urgent): on the home page, and (`compact`) above the map's area
    panel via /api/feed?urgent=1. Plain links, no Alpine, so it works fetched into the panel too.
    Nothing here is checked by us: the note says so.
--}}
@php($compact = $compact ?? false)
<section @class(['urgent-strip rounded-3xl border border-flag-red/30 bg-flag-red/5', 'p-4' => ! $compact, 'p-3' => $compact]) aria-labelledby="{{ $id = 'urgent-'.($compact ? 'area' : 'home') }}">
    <h2 id="{{ $id }}" @class(['flex items-center gap-2 font-bold text-red-text', 'text-lg' => ! $compact, 'text-sm' => $compact])><span class="live-dot" aria-hidden="true"></span>{{ __('জরুরি সাহায্য দরকার') }}</h2>
    <ul class="mt-2 space-y-1.5">
        @foreach ($posts as $p)
            <li data-item="post:{{ $p->id }}">
                <a href="{{ $p->url() }}" class="flex items-start gap-2 rounded-2xl bg-card px-3 py-2 transition hover:bg-paper-2">
                    @if ($p->blood_group)<span class="blood-pill shrink-0" title="{{ __('রক্তের গ্রুপ') }}"><span class="sr-only">{{ __('রক্তের গ্রুপ') }} </span>🩸 {{ $p->blood_group }}</span>@endif
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 font-semibold leading-snug">{{ $p->title }}</span>
                        <span class="mt-0.5 block text-xs text-ink-2">@if ($p->area)📍 {{ $p->area->name() }} · @endif{{ \App\Support\Lang::choice('আর :n ঘণ্টা', $p->urgentHoursLeft()) }}@if ($p->answers_count) · {{ \App\Support\Lang::choice(':nটি উত্তর', $p->answers_count) }}@endif</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-xs text-ink-2">{{ __('আমার বাংলাদেশ এসব তথ্য যাচাই করেনি। টাকা পাঠানো বা কোথাও যাওয়ার আগে নিজে যাচাই করুন।') }}</p>
</section>
