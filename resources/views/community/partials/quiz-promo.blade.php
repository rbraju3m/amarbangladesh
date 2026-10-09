{{-- The quiz as a pillar of the community: a compact invitation wherever people browse. --}}
<a href="{{ lroute('quiz', [], false) }}" @click="track('community_clicked', { meta: { to: 'quiz' } })" class="quiz-promo {{ $class ?? '' }}">
    <span class="text-2xl leading-none tracking-[-0.2em]" aria-hidden="true">🌿🌊🐅</span>
    <span class="min-w-0 flex-1">
        <span class="block font-bold">{{ __('তোমার বাংলাদেশ কোথায়?') }}</span>
        <span class="block text-sm opacity-90">{{ __('১ মিনিটের কুইজ: ৯টি জায়গার কোনটা আপনার?') }}</span>
    </span>
    <span class="shrink-0 text-xl" aria-hidden="true">→</span>
</a>
