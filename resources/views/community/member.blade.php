@extends('community.layout', [
    'active' => 'me',
    'pageTitle' => __(':title · আমার বাংলাদেশ', ['title' => $member->displayName()]),
    'ogTitle' => __(':title · আমার বাংলাদেশ', ['title' => $member->displayName()]),
    'noindex' => true,
])

@section('main')
<div class="mx-auto max-w-2xl px-4 pt-6">
    <div class="flex items-center gap-4">
        @include('community.partials.avatar', ['member' => $member, 'class' => 'size-16 text-2xl'])
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold" x-text="me === '{{ $member->code }}' && myName ? myName : @js($member->displayName())">{{ $member->displayName() }}</h1>
            <p class="text-sm text-ink-2">{{ __('যোগ দিয়েছেন :date', ['date' => \App\Support\Lang::date($member->created_at)]) }}</p>
        </div>
    </div>

    {{-- Contribution, not popularity --}}
    <dl class="mt-5 grid grid-cols-4 gap-2 text-center">
        @foreach ([['helpful', 'কাজে লেগেছে', '🙏'], ['solved', 'সমাধান', '✓'], ['answers', 'উত্তর', '💬'], ['posts', 'পোস্ট', '✍️']] as [$key, $label, $emoji])
            <div class="rounded-2xl border border-line bg-card px-1 py-3">
                <dd class="text-xl font-bold tabular-nums">{{ \App\Support\Lang::num($stats[$key]) }}</dd>
                <dt class="text-xs text-ink-2">{{ $emoji }} {{ __($label) }}</dt>
            </div>
        @endforeach
    </dl>

    {{-- Owner tools (decided in the browser) --}}
    <div x-show="me === '{{ $member->code }}'" x-cloak class="mt-5 rounded-3xl border border-line bg-card p-4" x-data="{ editing: false, recovery: false }">
        <div class="flex items-center justify-between gap-2">
            <p class="font-bold">{{ __('এটা আপনি 👋') }}</p>
            <button type="button" class="act-quiet" @click="editing = !editing">{{ __('✏️ নাম বদলান') }}</button>
        </div>
        <form x-show="editing" class="mt-3 flex gap-2" @submit.prevent="renameMe($el.name.value).then(ok => ok && (editing = false))">
            <input name="name" maxlength="20" :value="myName" class="field min-w-0 flex-1" aria-label="{{ __('নতুন নাম') }}">
            <button class="btn-primary !min-h-11 !w-auto !text-base" :disabled="busy">{{ __('সেভ') }}</button>
        </form>
        <button type="button" class="act-quiet mt-2 !px-0" @click="logout()">↩ {{ __('লগ আউট') }}</button>
    </div>

    <section class="mt-8">
        <h2 class="text-lg font-bold">{{ __('পোস্ট') }}</h2>
        <div class="mt-3 space-y-3">
            @forelse ($posts as $post)
                @include('community.partials.post-card', ['post' => $post, 'compact' => true])
            @empty
                <p class="rounded-2xl border border-dashed border-line px-4 py-5 text-center text-sm text-ink-2">{{ __('এখনো কোনো পোস্ট নেই।') }}</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8">
        <h2 class="text-lg font-bold">{{ __('উত্তর') }}</h2>
        <div class="mt-3 space-y-3">
            @forelse ($answers as $answer)
                <a href="{{ $answer->post->url() }}#answer-{{ $answer->id }}" class="block rounded-3xl border border-line bg-card p-4 transition hover:border-ink-2">
                    <p class="text-sm text-ink-2">{{ __('উত্তর দিয়েছেন:') }} <span class="font-semibold text-ink">{{ $answer->post->title }}</span></p>
                    <p class="mt-1 line-clamp-2">{{ \App\Community\Text::excerpt($answer->body) }}</p>
                    <p class="mt-2 flex gap-3 text-xs text-ink-2">
                        <span>{{ \App\Support\Lang::ago($answer->created_at) }}</span>
                        @if ($answer->helpful_count)<span>🙏 {{ \App\Support\Lang::num($answer->helpful_count) }}</span>@endif
                        @if ($answer->post->accepted_answer_id === $answer->id)<span class="font-semibold text-green-text">{{ __('✓ সমাধান') }}</span>@endif
                    </p>
                </a>
            @empty
                <p class="rounded-2xl border border-dashed border-line px-4 py-5 text-center text-sm text-ink-2">{{ __('এখনো কোনো উত্তর নেই।') }}</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
