{{-- Questions like the one being written (Ask page). They open in a new tab so the draft stays. --}}
<ul class="mt-2 space-y-1">
    @foreach ($posts as $post)
        <li>
            <a href="{{ $post->url() }}" target="_blank" rel="noopener" class="flex items-start gap-2 rounded-xl px-2 py-1.5 transition hover:bg-card">
                <span class="min-w-0 flex-1 leading-snug font-semibold">{{ $post->title }}</span>
                @if ($post->accepted_answer_id)
                    <span class="pill shrink-0 bg-flag-green/10 text-green-text">{{ __('✓ সমাধান হয়েছে') }}</span>
                @elseif ($post->answers_count)
                    <span class="pill shrink-0 bg-paper-2 text-ink">{{ \App\Support\Lang::choice(':nটি উত্তর', $post->answers_count) }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
