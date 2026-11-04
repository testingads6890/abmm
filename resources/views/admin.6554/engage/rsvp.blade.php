@extends('layouts.admin')
@section('title','Event RSVP & Attendance')
@section('content')
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Event</th><th>Date</th><th>RSVP</th><th>Capacity</th><th>Going (families)</th><th>Heads</th><th>Checked in</th><th></th></tr></thead><tbody>
@forelse($events as $e)@php $s=$stats[$e->id] ?? null; @endphp<tr><td>{{ $e->title }}</td><td>{{ $e->event_date?->format('d M Y') }}</td><td>{{ $e->rsvp_enabled ? 'On' : 'Off' }}</td><td>{{ $e->capacity ?: '∞' }}</td><td>{{ $s->fam ?? 0 }}</td><td>{{ $s->heads ?? 0 }}</td><td>{{ $s->in_ ?? 0 }}</td>
<td class="whitespace-nowrap"><a class="btn btn-sm" href="{{ route('admin.rsvp.show',$e) }}">Manage</a> @can('rsvp.scan')<a class="btn btn-sm" href="{{ route('admin.rsvp.scanner',$e) }}">Scanner</a>@endcan</td></tr>
@empty<tr><td colspan="8" style="color:var(--muted)">No events.</td></tr>@endforelse</tbody></table></div>
@endsection
