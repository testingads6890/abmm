@extends('layouts.public')
@section('title','Committee')
@section('content')
<div class="max-w-7xl mx-auto px-4 pt-10">
  <h1 class="font-display text-4xl">Committee</h1>
  <div class="flex flex-wrap gap-2 my-5">
    <a class="btn btn-sm {{ ! $level ? 'btn-ink' : 'btn-ghost' }}" href="{{ route('committee') }}">All</a>
    @foreach(['country'=>'Rashtriya','pradesh'=>'Pradesh','state'=>'State','district'=>'District','tehsil'=>'Tehsil'] as $k=>$l)
      <a class="btn btn-sm {{ $level===$k ? 'btn-ink' : 'btn-ghost' }}" href="{{ route('committee',['level'=>$k]) }}">{{ $l }}</a>@endforeach
  </div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($people as $p)
      <div class="card p-4 flex gap-4 items-center"><div class="w-14 h-14 shrink-0 rounded-full grid place-items-center text-white font-display text-xl" style="background:linear-gradient(135deg,var(--ink),var(--saffron))">{{ mb_substr($p->name,0,1) }}</div>
      <div class="min-w-0"><b class="block truncate">{{ $p->name }}</b><span class="badge">{{ $p->designation }}</span></div></div>
    @endforeach
  </div>
  <div class="mt-6">{{ $people->links() }}</div>
</div>
@endsection
