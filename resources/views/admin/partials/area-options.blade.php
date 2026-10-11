{{-- Every area as <option>s: divisions first, then each division's districts with their upazilas and city thanas under them. --}}
<optgroup label="Divisions">
    @foreach (array_filter(\App\Community\Taxonomy::areas(), fn ($a) => $a['type'] === 'division') as $a)
        <option value="{{ $a['slug'] }}" @selected($selected === $a['slug'])>{{ $a['name_en'] }} division (everything in it)</option>
    @endforeach
</optgroup>
@php($upazilas = \App\Community\Taxonomy::upazilasByDistrict())
@foreach (\App\Community\Taxonomy::districtsByDivision() as $division => $list)
    <optgroup label="{{ $division }} division">
        @foreach ($list as $a)
            <option value="{{ $a['slug'] }}" @selected($selected === $a['slug'])>{{ $a['name_en'] }} · {{ $a['name_bn'] }}</option>
            @foreach ($upazilas[$a['slug']] ?? [] as $u)
                <option value="{{ $u['slug'] }}" @selected($selected === $u['slug'])>&nbsp;&nbsp;&nbsp;— {{ $u['name_en'] }}{{ $u['type'] === 'thana' ? ' (thana)' : '' }} · {{ $u['name_bn'] }}</option>
            @endforeach
        @endforeach
    </optgroup>
@endforeach
