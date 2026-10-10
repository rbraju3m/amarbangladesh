{{--
    One division's districts, drawn into the home map's SVG when it zooms in (`GET /api/map/{division}`,
    PageController::mapDivision). Plain links (the `divisionMap` component listens on the group, so this
    HTML carries no Alpine): each district goes to its feed. $districts: DivisionMap::division()['districts'];
    $font: label size in viewBox units, so names read the same at every zoom.
--}}
@foreach ($districts as $i => $d)
    <a href="{{ lroute('feed', ['area' => $d['slug']], false) }}" class="district-link" data-district="{{ $d['slug'] }}"
        aria-label="{{ __(':name জেলা', ['name' => $d['name']]) }}: {{ \App\Support\Lang::choice(':nটি আলোচনা', $d['count']) }}">
        <path d="{{ $d['path'] }}" class="district level-{{ $d['level'] }}" style="--i: {{ $i }}" />
    </a>
@endforeach
@foreach ($districts as $i => $d)
    <text x="{{ $d['x'] }}" y="{{ $d['y'] }}" class="district-name" data-name="{{ $d['slug'] }}" style="--i: {{ $i }}; font-size: {{ $font }}px" text-anchor="middle" dominant-baseline="middle" aria-hidden="true">{{ $d['name'] }}</text>
    @if ($d['recent'])
        <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] + $font * 1.1 }}" r="{{ $font * 0.28 }}" class="district-live" aria-hidden="true" />
    @endif
@endforeach
