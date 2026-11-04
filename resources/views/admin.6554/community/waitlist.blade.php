@extends('layouts.admin')
@section('title','Waitlist: '.$e->title)
@section('content')
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Name</th><th>Mobile</th><th>Ticket</th><th>Qty</th><th>Status</th><th>Joined</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x->name }}</td><td>{{ $x->mobile }}</td><td>{{ $x->type }}</td><td>{{ $x->qty }}</td><td>{{ $x->status }}</td><td>{{ $x->created_at }}</td></tr>@empty<tr><td colspan="6" style="color:var(--muted)">The waitlist is empty.</td></tr>@endforelse</tbody></table></div>
@endsection
