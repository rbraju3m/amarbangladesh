@foreach ($notifications as $notification)
    @include('community.partials.notification', ['notification' => $notification])
@endforeach
