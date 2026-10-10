{{--
    Photos of a post (`big`) or an answer, in a grid. Each one links to the full image (works without
    JavaScript); with it, the shared viewer opens (`openPhotos` in community.js, partials/photo-viewer).
--}}
@php
    $big = $big ?? false;
    $count = $photos->count();
    $alt = fn ($i) => __('ছবি :n/:total: :title', ['n' => \App\Support\Lang::num($i + 1), 'total' => \App\Support\Lang::num($count), 'title' => $title]);
    $gallery = $photos->values()->map(fn ($p, $i) => ['src' => $p->url(), 'width' => $p->width, 'height' => $p->height, 'alt' => $alt($i)]);
@endphp
@if ($count)
    <div @class(['photo-grid', 'is-big' => $big, 'is-single' => $big && $count === 1, 'has-'.$count]) data-gallery='@json($gallery)'>
        @foreach ($photos->values() as $i => $photo)
            <a href="{{ $photo->url() }}" class="photo-cell" @click.prevent="openPhotos($el.closest('[data-gallery]'), {{ $i }})">
                <img src="{{ $photo->url($big && $count === 1 ? 'full' : 'thumb') }}"
                    @if ($big && $count === 1) srcset="{{ $photo->url('thumb') }} 480w, {{ $photo->url() }} 1600w" sizes="(min-width: 42rem) 40rem, 100vw" @endif
                    width="{{ $photo->width }}" height="{{ $photo->height }}" alt="{{ $alt($i) }}" loading="lazy" decoding="async">
            </a>
        @endforeach
    </div>
@endif
