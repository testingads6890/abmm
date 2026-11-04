@extends('layouts.admin')
@section('title','Feedback')
@section('content')
<div class="grid lg:grid-cols-3 gap-4">
<form method="post" action="{{ route('admin.feedback.store') }}" class="card p-5 grid gap-3 content-start">@csrf<h3 class="font-display text-lg">Give your feedback</h3><textarea class="input" name="message" rows="6" maxlength="2000" required placeholder="Suggestions for improvement, problems..."></textarea><button class="btn btn-primary">Submit</button></form>
<div class="lg:col-span-2"><form class="card p-4 grid sm:grid-cols-4 gap-3 mb-3"><input class="input" type="date" name="from" value="{{ request('from') }}"><input class="input" type="date" name="to" value="{{ request('to') }}"><button class="btn btn-ink">Search</button><a class="btn btn-ghost" href="{{ route('admin.feedback') }}">Reset</a></form>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>ID</th><th>Date</th><th>Name</th><th>Feedback</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $f)<tr><td data-l="ID">{{ $f->id }}</td><td data-l="Date">{{ $f->created_at->format('d-M-Y') }}</td><td data-l="Name">{{ $f->user?->name }}</td><td data-l="Feedback">{{ $f->message }}</td><td><form method="post" action="{{ route('admin.feedback.destroy',$f) }}" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">✕</button></form></td></tr>
@empty<tr><td colspan="5" class="py-6 text-center" style="color:var(--muted)">No data available in table</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $rows->links() }}</div></div></div>
@endsection
