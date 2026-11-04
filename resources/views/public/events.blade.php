@extends('layouts.public')
@section('title','Events')
@section('content')
<div class="max-w-4xl mx-auto px-4 pt-10"><h1 class="font-display text-4xl mb-6">Events</h1>
  <div class="grid gap-3">@forelse($events as $e)
    <div class="card p-5"><div class="flex flex-wrap justify-between gap-2"><b class="text-lg">{{ $e->title }}</b><span class="badge">{{ $e->event_date->format('d M Y') }}</span></div>
    <p class="text-sm mt-1" style="color:var(--muted)">📍 {{ $e->venue }} {{ $e->event_time ? '· '.\Illuminate\Support\Carbon::parse($e->event_time)->format('h:i A') : '' }}</p>@if($e->description)<p class="mt-2">{{ $e->description }}</p>@endif</div>
  @empty <p>No events.</p>@endforelse</div><div class="mt-6">{{ $events->links() }}</div></div>
@endsection
