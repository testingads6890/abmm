@extends('layouts.admin')
@section('title','Meeting list')
@section('content')
<div class="flex items-center justify-between mb-3">
  <h2 class="font-display text-xl">Meeting list</h2>
  @can('meetings.create')<a class="btn btn-ink btn-sm" href="{{ route('admin.meeting_list.create') }}">+ Add meeting</a>@endcan
</div>
<form method="get" class="card p-3 mb-3 flex flex-wrap gap-2">
  <select class="input" name="committee"><option value="">All committees</option>@foreach($committees as $k => $v)<option value="{{ $k }}" @selected(request('committee')===$k)>{{ $v }}</option>@endforeach</select>
  <select class="input" name="meeting_type"><option value="">All types</option>@foreach($types as $k => $v)<option value="{{ $k }}" @selected(request('meeting_type')===$k)>{{ $v }}</option>@endforeach</select>
  <select class="input" name="anchal"><option value="">All Anchal</option>@foreach($anchals as $a)<option value="{{ $a }}" @selected(request('anchal')===$a)>{{ $a }}</option>@endforeach</select>
  <select class="input" name="pradesh"><option value="">All Pradesh</option>@foreach($pradeshes as $p)<option value="{{ $p }}" @selected(request('pradesh')===$p)>{{ $p }}</option>@endforeach</select>
  <button class="btn btn-ink btn-sm">Filter</button>
</form>
<div class="card p-2 overflow-x-auto">
<table class="tbl stack"><thead><tr><th>Date</th><th>Time</th><th>Committee</th><th>Type</th><th>Pradesh</th><th>District</th><th>Anchal</th><th>Venue / Location</th><th>File</th><th>Submitted by</th><th></th></tr></thead><tbody>
@forelse($rows as $m)
<tr>
  <td data-l="Date">{{ $m->meeting_date->format('d-M-Y') }}</td>
  <td data-l="Time">{{ $m->meeting_time ? substr($m->meeting_time,0,5) : '-' }}</td>
  <td data-l="Committee">{{ $committees[$m->committee] ?? $m->committee }}</td>
  <td data-l="Type"><span class="badge">{{ $types[$m->meeting_type] ?? $m->meeting_type }}</span></td>
  <td data-l="Pradesh">{{ $m->pradesh }}</td>
  <td data-l="District">{{ $m->district }}</td>
  <td data-l="Anchal">{{ $m->anchal }}</td>
  <td data-l="Venue"><b>{{ $m->venue }}</b><br><small style="color:var(--muted)">{{ $m->location }}</small></td>
  <td data-l="File">@if($m->attachment)<a href="{{ asset('storage/'.$m->attachment) }}" target="_blank">View</a>@else - @endif</td>
  <td data-l="Submitted by">{{ $m->creator?->name ?? '-' }}<br><small style="color:var(--muted)">{{ $m->created_at?->format('d-M-Y H:i') }}</small></td>
  <td>@can('meetings.delete')<form method="post" action="{{ route('admin.meeting_list.destroy',$m) }}" onsubmit="return confirm('Remove this meeting?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">✕</button></form>@endcan</td>
</tr>
@empty
<tr><td colspan="10" class="py-6" style="color:var(--muted)">No meetings yet.</td></tr>
@endforelse
</tbody></table>
</div>
<div class="mt-3">{{ $rows->links() }}</div>
@endsection
