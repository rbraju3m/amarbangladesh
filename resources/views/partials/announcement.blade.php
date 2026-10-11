{{--
    One announcement bar (App\Models\Announcement). On public pages through <x-site.announcement>; the admin
    list shows it with `preview` (no close button, no script). A site link ("/feed…") is opened in the page's
    language. The ✕ hides it on this device: its id goes into localStorage['bd.dismissed'] and the inline script
    removes a dismissed bar before the first paint. Without JavaScript the bar just stays. `compact` (the quiz):
    one line on phones until tapped (app.css `.announcement`), so the quiz's play button stays above the fold.
--}}
@php($a = $announcement)
@php($warning = $a->level === 'warning')
@php($href = $a->link && str_starts_with($a->link, '/') && ! preg_match('#^/en(/|\?|$)#', $a->link) ? \App\Support\Lang::path($a->link) : $a->link)
<aside data-announcement="{{ $a->id }}" @if ($compact ?? false) data-compact @endif aria-label="{{ __('ঘোষণা') }}" @class(['border-b', 'border-warn/30 bg-warn/10' => $warning, 'border-line bg-paper-2' => ! $warning])>
    <div class="mx-auto flex max-w-5xl items-start gap-2.5 px-4 py-2 text-sm">
        <span @class(['mt-0.5 shrink-0', 'text-warn' => $warning, 'text-green-text' => ! $warning]) aria-hidden="true">
            @if ($warning)
                <svg viewBox="0 0 24 24" class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            @else
                <svg viewBox="0 0 24 24" class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v4h3l6 4V6L7 10H4Zm13-1a4 4 0 0 1 0 6"/></svg>
            @endif
        </span>
        <p class="announcement-text min-w-0 flex-1 leading-snug">
            @if ($warning)<span class="sr-only">{{ __('জরুরি:') }} </span>@endif{{ $a->text('text') }}
            @if ($href)<a href="{{ $href }}" class="ml-1 font-semibold whitespace-nowrap underline underline-offset-2" @if (! str_starts_with($href, '/')) target="_blank" rel="noopener" @endif>{{ __('বিস্তারিত') }} →</a>@endif
        </p>
        @unless ($preview ?? false)
            <button type="button" hidden data-dismiss class="-my-1 -mr-2 flex size-8 shrink-0 items-center justify-center rounded-full text-ink-2 transition hover:bg-paper hover:text-ink" aria-label="{{ __('ঘোষণাটি বন্ধ করুন') }}">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
            <script>try{(function(b){var k='bd.dismissed',id=+b.dataset.announcement,d=JSON.parse(localStorage.getItem(k)||'[]');if(d.indexOf(id)>-1)return b.remove();if(b.hasAttribute('data-compact'))b.querySelector('p').addEventListener('click',function(e){e.target.closest('a')||b.classList.toggle('is-open')});var x=b.querySelector('[data-dismiss]');x.hidden=false;x.addEventListener('click',function(){d=JSON.parse(localStorage.getItem(k)||'[]').filter(function(i){return i!==id});d.push(id);localStorage.setItem(k,JSON.stringify(d.slice(-20)));var c=document.getElementById('content');b.remove();c&&c.focus({preventScroll:true})})})(document.currentScript.closest('[data-announcement]'))}catch(e){}</script>
        @endunless
    </div>
</aside>
