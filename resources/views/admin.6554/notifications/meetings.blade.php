@extends('layouts.admin')
@section('title','My Meetings / Events')
@section('content')
<nav class="text-sm mb-3" style="color:var(--muted)">Dashboard / <b>Notification</b></nav>
<form class="card p-4 grid sm:grid-cols-4 gap-3 mb-3"><input class="input" type="date" name="from" value="{{ request('from') }}" required><input class="input" type="date" name="to" value="{{ request('to') }}" required><button class="btn btn-ink">Search</button><a class="btn btn-ghost" href="{{ route('admin.meetings') }}">Reset</a></form>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Notification</th><th>Date</th><th>Time</th><th>Venue</th><th>Sent By</th><th>Notification Date</th></tr></thead><tbody>
@forelse($rows as $n)<tr><td data-l="Notification"><b>{{ $n->title }}</b><div class="text-xs">{{ $n->message }}</div></td><td data-l="Date">{{ $n->event?->event_date?->format('d-M-Y') }}</td><td data-l="Time">{{ $n->event?->event_time ? \Illuminate\Support\Carbon::parse($n->event->event_time)->format('h:i A') : '' }}</td><td data-l="Venue">{{ $n->event?->venue }}</td><td data-l="By">{{ $n->sender?->name }}</td><td data-l="Sent">{{ $n->created_at->format('d-M-Y') }}</td></tr>
@empty<tr><td colspan="6" class="py-8 text-center" style="color:var(--muted)">No data available in table</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $rows->links() }}</div>
@endsection
