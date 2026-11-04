@extends('layouts.admin')
@section('title','Contact Messages')
@section('content')
<div class="grid gap-3">@forelse($rows as $m)<div class="card p-4"><div class="flex flex-wrap justify-between gap-2"><b>{{ $m->name }}</b><span class="text-xs" style="color:var(--muted)">{{ $m->created_at?->format('d M Y h:i A') }}</span></div><div class="text-sm" style="color:var(--muted)">{{ $m->mobile }} {{ $m->email }}</div><p class="mt-2 whitespace-pre-line">{{ $m->message }}</p></div>@empty<p>No messages.</p>@endforelse</div><div class="mt-4">{{ $rows->links() }}</div>
@endsection
