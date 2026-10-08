{{-- The আমার বাংলাদেশ emblem (redrawn from the Facebook-page logo on the accurate outline): ring, map, sun.
     Colours are tokens so it works in dark mode; static copy in public/images/brand/logo.svg. --}}
<svg viewBox="0 0 600 600" class="{{ $class ?? 'size-8' }}" aria-hidden="true">
    <circle cx="300" cy="310" r="215" fill="none" stroke="var(--green-text)" stroke-width="36" />
    <path d="M300 95 A215 215 0 0 1 471 179" fill="none" stroke="var(--red)" stroke-width="36" />
    <path d="@include('partials.bd-map-path')" transform="translate(116 56) scale(.92)" fill="var(--card)" stroke="var(--green-text)" stroke-width="22" stroke-linejoin="round" />
    <circle cx="292" cy="300" r="62" fill="var(--red)" />
</svg>
