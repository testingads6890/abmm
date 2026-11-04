@extends('layouts.admin')
@section('title','RSVP: '.$e->title)
@section('content')
<div class="grid sm:grid-cols-4 gap-3 mb-4">@foreach($sum as $k=>$v)<div class="card p-3"><div class="text-xs" style="color:var(--muted)">{{ ucwords(str_replace('_',' ',$k)) }}</div><div class="text-2xl font-display">{{ is_scalar($v) ? $v : '' }}</div></div>@endforeach</div>
@can('rsvp.edit')
<form method="post" action="{{ route('admin.rsvp.settings',$e) }}" class="card p-4 grid sm:grid-cols-5 gap-2 mb-4 items-end">@csrf
 <label class="text-sm"><input type="checkbox" name="rsvp_enabled" value="1" @checked($e->rsvp_enabled)> RSVP on</label>
 <label class="text-sm">Capacity<input class="input" type="number" name="capacity" value="{{ $e->capacity }}"></label>
 <label class="text-sm">Max guests<input class="input" type="number" name="max_guests" value="{{ $e->max_guests ?? 0 }}"></label>
 <label class="text-sm">RSVP closes<input class="input" type="date" name="rsvp_closes_on" value="{{ $e->rsvp_closes_on }}"></label>
 <label class="text-sm">Attend points<input class="input" type="number" name="attend_points" value="{{ $e->attend_points }}"></label>
 <div class="sm:col-span-5"><button class="btn btn-sm btn-ink">Save</button></div></form>
@endcan
<div class="flex gap-2 mb-3">@foreach(['xlsx'=>'Attendance Excel','csv'=>'CSV','pdf'=>'PDF'] as $f=>$l)<a class="btn btn-sm" href="{{ route('admin.rsvp.report',[$e,'export'=>$f]) }}">{{ $l }}</a>@endforeach
 @can('rsvp.edit')<form class="inline" method="post" action="{{ route('admin.rsvp.certificates',$e) }}">@csrf<button class="btn btn-sm">Attendance certificates</button></form>@endcan</div>
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Name</th><th>Mobile</th><th>RSVP</th><th>Guests</th><th>Attendance</th></tr></thead><tbody>
@foreach($rows as $x)<tr><td>{{ $x->name }}</td><td>{{ $x->mobile }}</td><td>{{ $x->status }}</td><td>{{ $x->guests }}</td><td>{{ $x->checked_in_at ? 'Present '.$x->checked_in_at : 'Absent' }}</td></tr>@endforeach</tbody></table>{{ $rows->links() }}</div>
@endsection
