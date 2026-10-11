{{--
    A post's or answer's video. Ready: a native player that downloads nothing until play is pressed
    (mobile data), with the poster. Still converting: a note that the browser swaps for the player once
    it's ready (`watchVideos` in community.js). Conversion failed: only its author is told (`owner` =
    "post:ID" / "answer:ID", the page's `owned` map), so they can edit it away.
--}}
@if ($video ?? null)
    @if ($video->isReady())
        <figure class="video-box" style="aspect-ratio: {{ $video->width }} / {{ $video->height }}">
            <video controls preload="none" playsinline poster="{{ $video->posterUrl() }}" width="{{ $video->width }}" height="{{ $video->height }}"
                aria-label="{{ __('ভিডিও (:length): :title', ['length' => $video->length(), 'title' => $title]) }}">
                <source src="{{ $video->url() }}" type="video/mp4">
                <a href="{{ $video->url() }}">{{ __('ভিডিওটা দেখুন') }}</a>
            </video>
        </figure>
    @elseif ($video->isPending())
        <p class="video-pending" role="status" data-video-pending="{{ $video->id }}">🎬 {{ __('ভিডিও তৈরি হচ্ছে… একটু পরে এখানে দেখা যাবে।') }}</p>
    @else
        <p x-show="owned['{{ $owner }}']" x-cloak class="rounded-2xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">{{ __('ভিডিওটা চালানো গেলো না। সম্পাদনা করে সরিয়ে দিন বা নতুন একটা দিন।') }}</p>
    @endif
@endif
