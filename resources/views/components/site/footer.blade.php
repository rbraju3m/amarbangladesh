{{-- The site footer on every public page. Neutral copy (no তুমি/আপনি), since quiz and community pages share it. --}}
<footer {{ $attributes->merge(['class' => 'mt-16 border-t border-line']) }}>
    <div class="mx-auto flex max-w-5xl flex-col gap-5 px-4 py-8 text-sm text-ink-2 md:flex-row md:items-start md:justify-between">
        <div class="max-w-xs">
            <a href="{{ lroute('home') }}" class="flex items-center gap-2 font-bold text-ink">@include('partials.logo', ['class' => 'size-7'])<span>{{ __('আমার বাংলাদেশ') }}</span></a>
            <p class="mt-2 leading-relaxed">{{ __('বাংলাদেশের মানুষের প্রশ্ন, উত্তর আর অভিজ্ঞতা।') }}</p>
        </div>
        <nav class="grid grid-cols-2 gap-x-10 gap-y-2 sm:flex sm:gap-6" aria-label="{{ __('ফুটার') }}">
            @foreach (\App\Support\SiteNav::items() as $item)
                @continue($item['key'] === 'me')
                <a href="{{ $item['href'] }}" class="hover:text-ink hover:underline">{{ __($item['label']) }}</a>
            @endforeach
            <a href="{{ lroute('privacy', [], false) }}" class="hover:text-ink hover:underline">{{ __('গোপনীয়তা') }}</a>
            <a href="mailto:{{ config('admin.privacy_email') }}" class="hover:text-ink hover:underline">{{ __('যোগাযোগ') }}</a>
        </nav>
        @include('partials.lang-switch', ['class' => 'self-start'])
    </div>
</footer>
