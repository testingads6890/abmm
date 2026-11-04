@extends('layouts.public')
@section('title','Business Directory')
@section('content')
<div class="max-w-7xl mx-auto px-4 pt-10">
  <h1 class="font-display text-4xl">Business Directory</h1>
  <form class="grid sm:grid-cols-[1fr_220px_auto] gap-3 my-5">
    <input class="input" name="q" value="{{ request('q') }}" placeholder="Search by name, type or city">
    <select class="input" name="type"><option value="">All types</option>@foreach($types as $t)<option @selected(request('type')===$t)>{{ $t }}</option>@endforeach</select>
    <button class="btn btn-primary">Search</button>
  </form>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($list as $b)
      <div class="card p-5"><div class="flex justify-between"><span class="badge">{{ $b->type }}</span>@if($b->rating)<span class="text-sm">★ {{ number_format($b->rating,1) }}</span>@endif</div>
      <b class="block text-lg mt-2">{{ $b->name }}</b><p class="text-sm mt-1" style="color:var(--muted)">{{ $b->address }}</p>
      <a class="btn btn-ghost btn-sm mt-3" href="tel:{{ $b->mobile }}">📞 {{ $b->mobile }}</a></div>
    @empty <p style="color:var(--muted)">No business found.</p> @endforelse
  </div>
  <div class="mt-6">{{ $list->links() }}</div>
</div>
@endsection
