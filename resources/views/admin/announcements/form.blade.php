@extends('layouts.admin')
@section('title', 'Edit announcement')
@section('content')
<header class="mb-6">
    <a href="{{ route('admin.announcements.index') }}" class="text-sm text-ink-2 hover:underline">← Announcements</a>
    <h1 class="mt-1 text-2xl font-bold md:text-3xl">Edit announcement</h1>
</header>

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif

<form method="POST" action="{{ route('admin.announcements.update', $announcement) }}" class="panel max-w-3xl" data-dirty-guard>
    @csrf @method('PUT')
    @include('admin.announcements.fields', ['announcement' => $announcement])
    <button class="btn mt-4">Save</button>
</form>
@endsection
