{{-- Who wrote a post or answer, as the public sees it. Anonymous items show no name, link or member code. --}}
@php($author = $item->publicAuthor())
@if ($author)
    <a href="{{ lroute('members.show', $author, false) }}" class="{{ $linkClass ?? '' }} flex min-w-0 items-center gap-2 font-semibold hover:text-green-text">
        @include('community.partials.avatar', ['member' => $author, 'class' => $avatarClass ?? 'size-8 text-sm'])
        <span class="truncate">{{ $author->displayName() }}</span>
    </a>
@else
    <span class="{{ $linkClass ?? '' }} flex min-w-0 items-center gap-2 font-semibold">
        <span class="avatar {{ $avatarClass ?? 'size-8 text-sm' }} bg-ink-2" aria-hidden="true">🕶️</span>
        <span class="truncate">{{ __('বেনামী') }}</span>
    </span>
@endif
