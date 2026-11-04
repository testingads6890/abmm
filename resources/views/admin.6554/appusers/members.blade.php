@extends('layouts.admin')
@section('title','App Registered Members')
@section('content')
@include('admin.appusers._summary')
<form class="card p-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-3">
 <input class="input lg:col-span-2" name="q" value="{{ request('q') }}" placeholder="Name or mobile">
 <select class="input" name="state"><option value="">All States</option>@foreach($summary as $s)<option value="{{ $s->st }}" @selected(request('state')===$s->st)>{{ $s->st==='__none' ? 'Unmapped' : $s->st }}</option>@endforeach</select>
 <select class="input" name="approval"><option value="">All</option>@foreach(['approved','pending','rejected'] as $a)<option @selected(request('approval')===$a)>{{ $a }}</option>@endforeach</select>
 <button class="btn btn-ink">Filter</button>
</form>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Member</th><th>Mobile</th><th>Relation</th><th>Family</th><th>State / District</th><th>Status</th></tr></thead><tbody>
@forelse($rows as $m)<tr><td data-l="Member"><b>{{ $m->surname }} {{ $m->name }}</b><div class="text-xs" style="color:var(--muted)">{{ $m->member_code }} · {{ $m->gender }} · {{ $m->dob?->age }} yrs</div></td><td data-l="Mobile">{{ $m->mobile }}</td><td data-l="Relation">{{ $m->is_head ? 'Head of Family' : $m->relation }}</td>
<td data-l="Family">{{ $m->family?->family_code }}</td><td data-l="State" class="text-xs">{{ $m->family?->state ?: 'Unmapped' }} / {{ $m->family?->district }}</td>
<td data-l="Status"><span class="badge {{ $m->approval==='approved' ? 'badge-ok' : 'badge-warn' }}">{{ $m->approval }}</span>@if($m->status==='archived') <span class="badge badge-warn">{{ $m->archive_type }}</span>@endif</td></tr>
@empty<tr><td colspan="6" class="text-center py-8" style="color:var(--muted)">Nothing found.</td></tr>@endforelse</tbody></table></div>
<div class="my-4">{{ $rows->links() }}</div>
@endsection
