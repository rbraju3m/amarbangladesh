@foreach ($posts as $post)
    @include('community.partials.post-card', ['post' => $post, 'compact' => $compact ?? false])
    @if (($promoAt ?? null) === $loop->iteration && ! $loop->last)
        @include('community.partials.quiz-promo', ['class' => 'lg:hidden'])
    @endif
@endforeach
