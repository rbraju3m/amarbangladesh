@extends('community.layout', [
    'active' => null,
    'pageTitle' => $area ? __(':place-এর হেল্পলাইন · আমার বাংলাদেশ', ['place' => $area['name']]) : __('হেল্পলাইন · আমার বাংলাদেশ'),
    'ogTitle' => __('হেল্পলাইন · আমার বাংলাদেশ'),
    'ogDescription' => __('বিপদে বা দরকারে যেসব সরকারি ও জরুরি নম্বরে ফোন করা যায়।'),
])

{{-- Admin-managed numbers (only checked ones are on). Plain links and a GET form: works without JS. --}}
@section('main')
@php($areaNames = collect(\App\Community\Taxonomy::areas())->pluck('name', 'id'))
<div class="mx-auto max-w-2xl px-4 pt-6">
    <h1 class="text-2xl font-bold md:text-3xl">📞 {{ $area ? __(':place-এর হেল্পলাইন', ['place' => $area['name']]) : __('হেল্পলাইন') }}</h1>
    <p class="mt-1 text-ink-2">{{ __('বিপদে বা দরকারে যেসব নম্বরে ফোন করা যায়। নম্বরে চাপলেই ফোন যাবে।') }}</p>

    <form method="get" action="{{ lroute('helplines', [], false) }}" class="mt-4">
        <label for="helpline-area" class="text-sm font-semibold">{{ __('এলাকা') }}</label>
        <div class="mt-1 flex items-center gap-2">
            <select id="helpline-area" name="area" class="area-select" onchange="this.form.submit()">
                <option value="">{{ __('📍 সারা দেশ') }}</option>
                <optgroup label="{{ __('বিভাগ') }}">
                    @foreach ($divisions as $d)
                        <option value="{{ $d['slug'] }}" @selected(($area['slug'] ?? null) === $d['slug'])>{{ __(':name বিভাগ', ['name' => $d['name']]) }}</option>
                    @endforeach
                </optgroup>
                @foreach ($districts as $division => $list)
                    <optgroup label="{{ __(':name বিভাগ', ['name' => $division]) }}">
                        @foreach ($list as $a)
                            <option value="{{ $a['slug'] }}" @selected(($area['slug'] ?? null) === $a['slug'])>{{ $a['name'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <noscript><button class="text-sm font-semibold">{{ __('দেখান') }}</button></noscript>
        </div>
        @if ($area)<p class="mt-1 text-xs text-ink-2">{{ __('সারা দেশের নম্বরের সাথে এই এলাকার নিজের নম্বরও দেখানো হচ্ছে।') }}</p>@endif
    </form>

    @forelse ($helplines as $kind => $rows)
        <section class="mt-8" aria-labelledby="kind-{{ $kind }}">
            <h2 id="kind-{{ $kind }}" class="text-lg font-bold">{{ \App\Models\Helpline::KINDS[$kind][1] ?? '📞' }} {{ \App\Models\Helpline::kindLabel($kind) }}</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($rows as $h)
                    @php($en = \App\Support\Lang::isEnglish())
                    @php($name = $en && filled($h['name_en']) ? $h['name_en'] : $h['name_bn'])
                    @php($note = $en && filled($h['note_en']) ? $h['note_en'] : $h['note_bn'])
                    <li id="helpline-{{ $h['id'] }}" class="flex items-center gap-4 rounded-2xl border border-line bg-card px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold">{{ $name }}</h3>
                            @if ($note)<p class="text-sm text-ink-2">{{ $note }}</p>@endif
                            <p class="mt-1 text-xs text-ink-2">
                                @if ($h['area_id'])<span>📍 {{ $areaNames[$h['area_id']] ?? '' }}</span> · @endif
                                @if ($h['last_checked_at'])<span>{{ __('শেষ যাচাই: :date', ['date' => \App\Support\Lang::date(\Illuminate\Support\Carbon::parse($h['last_checked_at']))]) }}</span>@endif
                            </p>
                        </div>
                        <a href="{{ \App\Models\Helpline::telHref($h['number']) }}" class="btn-primary !min-h-12 !w-auto shrink-0 !px-4 tabular-nums" aria-label="{{ __(':name-এ ফোন করুন: :number', ['name' => $name, 'number' => $h['number']]) }}">📞 {{ \App\Models\Helpline::display($h['number']) }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="mt-8 rounded-3xl border border-dashed border-line px-5 py-8 text-center">
            <p class="text-4xl" aria-hidden="true">📞</p>
            <p class="mt-2 font-semibold">{{ __('তালিকাটা এখনো যাচাই হচ্ছে') }}</p>
            <p class="mt-1 text-sm text-ink-2">{{ __('প্রতিটা নম্বর যাচাই করে তারপর এখানে দেওয়া হবে।') }}</p>
        </div>
    @endforelse

    <p class="mt-8 rounded-2xl bg-paper-2 px-4 py-3 text-sm text-ink-2">{{ __('নম্বরগুলো আমরা যাচাই করে দিই, তবু বদলে যেতে পারে। কোনো নম্বর কাজ না করলে জানান:') }} <a href="mailto:{{ config('admin.privacy_email') }}" class="font-semibold underline">{{ config('admin.privacy_email') }}</a></p>
</div>
@endsection
