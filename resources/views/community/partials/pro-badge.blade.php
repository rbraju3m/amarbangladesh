{{-- The professional badge an admin gave a member after checking (Member::badge()). Never on anonymous items. --}}
@if ($badge = $member->badge())
    <span class="pro-badge" title="{{ __('পেশা যাচাই করেছে আমার বাংলাদেশ') }}"><span aria-hidden="true">✔</span> <span class="truncate">{{ $badge }}</span><span class="sr-only"> · {{ __('পেশা যাচাই করেছে আমার বাংলাদেশ') }}</span></span>
@endif
