@extends("layouts.app")
@section("pageTitle", $page->title ?? "Page")
@section("content")

<div class="container py-4">
    <h1 class="mb-4">{{ $page->title }}</h1>
    <div class="card shadow">
        <div class="card-body prose">
            {!! $page->content !!}
        </div>
    </div>
</div>

@endsection
