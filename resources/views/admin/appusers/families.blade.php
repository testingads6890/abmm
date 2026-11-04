@extends('layouts.admin')
@section('title','App Registered Families')
@section('content')
@include('admin.appusers._summary')
<form class="card p-4 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-3">
 <input class="input lg:col-span-2" name="q" value="{{ request('q') }}" placeholder="Surname, name, mobile or Family ID">
 <select class="input" name="state"><option value="">All States</option>@foreach($summary as $s)<option value="{{ $s->st }}" @selected(request('state')===$s->st)>{{ $s->st==='__none' ? 'Unmapped' : $s->st }}</option>@endforeach</select>
 <select class="input" name="district"><option value="">All Districts</option>@foreach($districts as $d)<option @selected(request('district')===$d)>{{ $d }}</option>@endforeach</select>
 <input class="input" type="date" name="from" value="{{ request('from') }}" title="From"><input class="input" type="date" name="to" value="{{ request('to') }}" title="To">
 <button class="btn btn-ink">Filter</button><a class="btn" href="{{ route('admin.appusers.families') }}">Reset</a>
 @can('appusers.export')<div class="col-span-full flex gap-2 text-sm">Export: @foreach(['xlsx','csv','pdf'] as $x)<a class="badge" href="{{ request()->fullUrlWithQuery(['export'=>$x]) }}">{{ strtoupper($x) }}</a>@endforeach</div>@endcan
</form>
<div class="mb-2 text-sm"><b>Total:</b> {{ number_format($rows->total()) }}</div>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Family ID</th><th>Head</th><th>Mobile</th><th>Members</th><th>Location</th><th>Registered</th><th></th></tr></thead><tbody>
@forelse($rows as $f)<tr><td data-l="Family ID">{{ $f->family_code }}</td><td data-l="Head"><b>{{ $f->surname }} {{ $f->head_name }}</b></td><td data-l="Mobile">{{ $f->mobile }}</td><td data-l="Members">{{ $f->member_count }}</td>
<td data-l="Location" class="text-xs">{{ $f->tehsil ?: $f->city }}, {{ $f->district }}<br>{{ $f->state ?: 'Unmapped' }} @if($f->pradesh)· ABMM {{ $f->pradesh }}@endif</td><td data-l="Registered" class="text-xs">{{ $f->app_registered_at?->format('d-M-Y H:i') }}</td>
<td>@can('families.view')<a class="act act-ok" title="View" href="{{ route('admin.families.show',$f) }}">🔍</a>@endcan</td></tr>
@empty<tr><td colspan="7" class="text-center py-8" style="color:var(--muted)">No app registrations yet.</td></tr>@endforelse</tbody></table></div>
<div class="my-4">{{ $rows->links() }}</div>
@endsection
