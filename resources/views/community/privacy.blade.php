@extends('community.layout', [
    'active' => null,
    'pageTitle' => __('গোপনীয়তা · আমার বাংলাদেশ'),
    'ogTitle' => __('গোপনীয়তা · আমার বাংলাদেশ'),
    'ogDescription' => __('আমরা কী রাখি, কেন রাখি, আর আপনি কী করতে পারেন।'),
])

{{-- The text is in one view per language (long prose reads better whole than as hundreds of keys). Keep both in sync with the code. --}}
@section('main')
<article class="privacy mx-auto max-w-2xl px-4 pt-6 pb-4">
    <h1 class="text-[1.9rem] leading-tight font-bold md:text-4xl">{{ __('গোপনীয়তা') }}</h1>
    <p class="mt-1 text-sm text-ink-2">{{ __('সর্বশেষ বদল: :date', ['date' => \App\Support\Lang::date($updated)]) }}</p>
    @include('community.privacy.'.\App\Support\Lang::current(), ['email' => $email])
</article>
@endsection
