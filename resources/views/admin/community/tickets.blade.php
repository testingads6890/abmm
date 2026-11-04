@extends('layouts.admin')
@section('title','Event ticketing & online events')
@section('content')
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Event</th><th>Date</th><th>Ticketing</th><th>Online</th><th>Sold</th><th>Revenue</th><th>In</th><th>Waitlist</th><th></th></tr></thead><tbody>
@foreach($events as $e)@php $s=$sales[$e->id]; @endphp<tr>
 <form method="post" action="{{ route('admin.tickets.settings',$e) }}">@csrf
 <td>{{ $e->title }}</td><td>{{ $e->event_date?->format('d M Y') }}</td><td><input type="checkbox" name="ticketing" value="1" @checked($e->ticketing)></td>
 <td class="whitespace-nowrap"><select class="input !w-24" name="online_provider"><option value="">—</option>@foreach(['meet'=>'Meet','zoom'=>'Zoom','live'=>'Live'] as $k=>$l)<option value="{{ $k }}" @selected($e->online_provider===$k)>{{ $l }}</option>@endforeach</select> <input class="input !w-56" name="online_url" value="{{ $e->online_url }}" placeholder="https://..."></td>
 <td>{{ $s['tickets'] }}</td><td>{{ number_format($s['revenue'],2) }}</td><td>{{ $s['checked_in'] }}</td><td>{{ $s['waitlist'] }}</td>
 <td class="whitespace-nowrap">@can('ticketing.edit')<button class="btn btn-sm btn-ink">Save</button>@endcan
  @can('ticketing.scan')<a class="btn btn-sm" href="{{ route('admin.tickets.scanner',$e) }}">Scanner</a>@endcan
  <a class="btn btn-sm" href="{{ route('admin.tickets.waitlist',$e) }}">Waitlist</a><a class="btn btn-sm" href="{{ route('admin.tickets.attendees',[$e,'export'=>'xlsx']) }}">Attendees</a></td></form></tr>@endforeach</tbody></table></div>
@endsection
