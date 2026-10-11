{{-- The current site-wide announcement, if any (App\Models\Announcement::current(), cached), right under the header. --}}
@props(['compact' => false])
@php($announcement = \App\Models\Announcement::current())
@if ($announcement)
    <div data-site-announcement {{ $attributes }}>@include('partials.announcement', ['announcement' => $announcement, 'compact' => $compact])</div>
@endif
