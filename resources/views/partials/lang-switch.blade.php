{{-- Switch to the same page in the other language. The label is in the language it switches to. --}}
@if (\App\Support\Lang::isEnglish())
    <a href="{{ \App\Support\Lang::switchUrl('bn') }}" hreflang="bn" lang="bn" class="lang-switch {{ $class ?? '' }}" aria-label="বাংলায় পড়ুন">বাংলা</a>
@else
    <a href="{{ \App\Support\Lang::switchUrl('en') }}" hreflang="en" lang="en" class="lang-switch {{ $class ?? '' }}" aria-label="Read in English">English</a>
@endif
