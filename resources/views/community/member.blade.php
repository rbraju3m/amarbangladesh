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
    <div x-show="me === '{{ $member->code }}'" x-cloak class="mt-5 rounded-3xl border border-line bg-card p-4" x-data="{ editing: false, recovery: false, removing: false }">
        <div class="flex items-center justify-between gap-2">
            <p class="font-bold">{{ __('এটা আপনি 👋') }}</p>
            <button type="button" class="act-quiet" @click="editing = !editing">{{ __('✏️ নাম বদলান') }}</button>
        </div>
        <a href="{{ lroute('notifications', [], false) }}" class="act mt-3" :class="unread && '!border-flag-red'">
            🔔 {{ __('নোটিফিকেশন') }}
            <span x-show="unread" class="rounded-full bg-flag-red px-2 text-xs font-bold text-white tabular-nums" x-text="bn(unread)"></span>
        </a>
        <a href="{{ lroute('saved', [], false) }}" class="act mt-3">🔖 {{ __('সেভ করা পোস্ট') }}</a>
        <form x-show="editing" class="mt-3 flex gap-2" @submit.prevent="renameMe($el.name.value).then(ok => ok && (editing = false))">
            <input name="name" maxlength="20" :value="myName" class="field min-w-0 flex-1" aria-label="{{ __('নতুন নাম') }}">
            <button class="btn-primary !min-h-11 !w-auto !text-base" :disabled="busy">{{ __('সেভ') }}</button>
        </form>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="act-quiet !px-0" @click="logout()">↩ {{ __('লগ আউট') }}</button>
            <button type="button" class="act-quiet !px-0 text-ink-2" @click="removing = !removing" :aria-expanded="removing">{{ __('অ্যাকাউন্ট মুছে ফেলুন') }}</button>
        </div>
        {{-- Two steps, so one stray tap can't delete an account --}}
        <form x-show="removing" x-cloak class="mt-3 rounded-2xl border border-flag-red/40 p-3.5 text-sm" @submit.prevent="deleteAccount($el.content.checked)">
            <p class="font-bold">{{ __('অ্যাকাউন্ট মুছে ফেলবেন?') }}</p>
            <p class="mt-1 leading-relaxed text-ink-2">{{ __('আপনার লগইন, ইমেইল, ফোন নম্বর, নোটিফিকেশন আর “কাজে লেগেছে” চিহ্ন মুছে যাবে। পোস্ট আর উত্তর থেকে যাবে, লেখক দেখাবে “মুছে ফেলা অ্যাকাউন্ট”। এটা ফেরানো যাবে না।') }}
                <a href="{{ lroute('privacy', [], false) }}#delete" class="font-semibold underline hover:text-ink">{{ __('বিস্তারিত') }}</a></p>
            <label class="mt-3 flex items-start gap-2">
                <input type="checkbox" name="content" class="mt-1 size-4 accent-flag-red">
                <span>{{ __('আমার পোস্ট আর উত্তরও মুছে দিন') }}</span>
            </label>
            <div class="mt-3 flex gap-2">
                <button class="btn-primary !min-h-11 !w-auto !text-base" :disabled="busy">{{ __('হ্যাঁ, মুছে ফেলুন') }}</button>
                <button type="button" class="btn-ghost !min-h-11 !w-auto !text-base" @click="removing = false">{{ __('থাক') }}</button>
            </div>
        </form>
    </div>

    {{-- Posts / answers: one list at a time, endless with JavaScript --}}
    <nav class="mt-8 flex gap-1 border-b border-line" aria-label="{{ __('তালিকা') }}">
        @foreach (['posts' => 'পোস্ট', 'answers' => 'উত্তর'] as $key => $label)
            <a href="{{ lroute('members.show', ['member' => $member] + ($key === 'posts' ? [] : ['tab' => $key]), false) }}" @class(['feed-tab', 'is-on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>
                {{ __($label) }} <span class="tabular-nums text-ink-2">{{ \App\Support\Lang::num($stats[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <h2 class="sr-only">{{ $tab === 'posts' ? __('পোস্ট') : __('উত্তর') }}</h2>
    <div id="member-list" class="mt-4 space-y-3">
        @forelse ($items as $item)
            @if ($tab === 'posts')
                @include('community.partials.post-card', ['post' => $item, 'compact' => true])
            @else
                <a href="{{ $item->post->url() }}#answer-{{ $item->id }}" data-item="answer:{{ $item->id }}" class="block rounded-3xl border border-line bg-card p-4 transition hover:border-ink-2">
                    <p class="text-sm text-ink-2">{{ __('উত্তর দিয়েছেন:') }} <span class="font-semibold text-ink">{{ $item->post->title }}</span></p>
                    <p class="mt-1 line-clamp-2">{{ \App\Community\Text::excerpt($item->body) }}</p>
                    <p class="mt-2 flex gap-3 text-xs text-ink-2">
                        <span>{{ \App\Support\Lang::ago($item->created_at) }}</span>
                        @if ($item->helpful_count)<span>🙏 {{ \App\Support\Lang::num($item->helpful_count) }}</span>@endif
                        @if ($item->post->accepted_answer_id === $item->id)<span class="font-semibold text-green-text">{{ __('✓ সমাধান') }}</span>@endif
                    </p>
                </a>
            @endif
        @empty
            <p class="rounded-2xl border border-dashed border-line px-4 py-5 text-center text-sm text-ink-2">{{ $tab === 'posts' ? __('এখনো কোনো পোস্ট নেই।') : __('এখনো কোনো উত্তর নেই।') }}</p>
        @endforelse
    </div>
    @include('community.partials.more', ['list' => '#member-list', 'href' => $items->nextPageUrl()])
</div>
@endsection
