@extends('community.layout', [
    'active' => 'me',
    'pageTitle' => 'আমি · আমার বাংলাদেশ',
    'noindex' => true,
])

{{-- The browser holds the identity: forward to the member page, or restore one from a private link (#t=…). --}}
@section('main')
<div class="mx-auto max-w-md px-4 pt-10 text-center" x-init="openMe()">
    <div x-show="!me" x-cloak>
        <p class="text-5xl" aria-hidden="true">👋</p>
        <h1 class="mt-3 text-2xl font-bold">তুমি এখনো কিছু লেখোনি</h1>
        <p class="mt-2 text-ink-2">প্রথম প্রশ্ন বা উত্তর দিলেই তোমার পাতা তৈরি হবে। কোনো লগইন, ফোন নম্বর বা ইমেইল লাগে না।</p>
        <a href="{{ route('ask', [], false) }}" class="btn-primary mt-6">✍️ কিছু জিজ্ঞেস করি</a>
        <a href="{{ route('feed', [], false) }}" class="btn-ghost mt-3 w-full">আলোচনা দেখি</a>
        <p class="mt-6 text-xs leading-relaxed text-ink-2">আগে অন্য ব্রাউজারে লিখেছিলে? সেখানে তোমার পাতা থেকে “গোপন লিংক” কপি করে এখানে খোলো।</p>
    </div>
    <p x-show="me" class="text-ink-2">তোমার পাতা খুলছি…</p>
</div>
@endsection
