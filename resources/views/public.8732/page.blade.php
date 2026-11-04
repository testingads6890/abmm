@extends('layouts.public')
@section('title',$title)
@section('content')<div class="max-w-3xl mx-auto px-4 pt-10"><h1 class="font-display text-4xl">{{ $title }}</h1><div class="card p-6 mt-5 whitespace-pre-line">{{ $body }}</div></div>@endsection
