@extends('layouts.admin')
@section('title','My Meetings / Events')
@section('content')
<div class="grid lg:grid-cols-3 gap-4"><div class="card p-2 lg:col-span-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Date</th><th>Title</th><th>Venue</th><th>Type</th><th></th></tr></thead><tbody>
@forelse($rows as $e)<tr><td data-l="Date">{{ $e->event_date->format('d-M-Y') }}</td><td data-l="Title"><b>{{ $e->title }}</b></td><td data-l="Venue">{{ $e->venue }}</td><td data-l="Type"><span class="badge">{{ $e->kind }}</span></td>
<td><form method="post" action="{{ route('admin.events.destroy',$e) }}" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">✕</button></form></td></tr>@empty<tr><td colspan="5" class="py-6" style="color:var(--muted)">No data.</td></tr>@endforelse</tbody></table></div>
<form method="post" action="{{ route('admin.events.store') }}" class="card p-5 grid gap-3 content-start">@csrf<h3 class="font-display text-lg">New event</h3>
<input class="input" name="title" placeholder="Title" required><textarea class="input" name="description" rows="3" placeholder="Description"></textarea><input class="input" name="venue" placeholder="Sthan" required>
<div class="grid grid-cols-2 gap-2"><input class="input" type="date" name="event_date" required><input class="input" type="time" name="event_time"></div>
<select class="input" name="kind"><option value="event">Event</option><option value="meeting">Meeting</option><option value="parichay">Parichay Sammelan</option></select><select class="input" name="series"><option value="">Normal</option><option value="financial_literacy">Financial literacy webinar</option></select><select class="input" name="audience">@foreach(['country','pradesh','state','district','tehsil'] as $a)<option>{{ $a }}</option>@endforeach</select>
<input class="input" name="audience_value" placeholder="Audience value"><label class="text-sm flex gap-2"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" checked class="accent-orange-600"> Show on website</label><button class="btn btn-primary">Save</button></form></div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
