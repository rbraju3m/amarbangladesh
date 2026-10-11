{{-- What a member proved they own when signing in (Member::contactBadges). Says the contact was checked, not the person. --}}
@php($contacts = $contacts ?? $member->contactBadges())
@if ($contacts)
    <div class="{{ $class ?? '' }} flex flex-wrap items-center gap-1.5">
        @foreach ($contacts as $contact)
            <span class="contact-badge" title="{{ __('নম্বর বা ঠিকানাটা যাচাই করা, মানুষটার পরিচয় না') }}">{{ $contact === 'phone' ? '📱 '.__('ফোন নম্বর যাচাই করা') : '✉️ '.__('ইমেইল যাচাই করা') }}</span>
        @endforeach
    </div>
@endif
