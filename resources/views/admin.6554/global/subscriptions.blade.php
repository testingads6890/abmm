@extends('layouts.admin')
@section('title','Subscribers')
@section('content')
<form method="get" class="flex flex-wrap gap-2 mb-3"><select class="input !w-40" name="status"><option value="">All status</option>@foreach(['pending','active','expired','cancelled'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
 <select class="input !w-56" name="audience"><option value="">All audiences</option>@foreach(\App\Models\Plan::AUDIENCES as $k=>$l)<option value="{{ $k }}" @selected(request('audience')===$k)>{{ $l }}</option>@endforeach</select><button class="btn btn-sm btn-ink">Filter</button></form>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>#</th><th>Subscriber</th><th>Plan</th><th>For</th><th>Status</th><th>From</th><th>Until</th></tr></thead><tbody>
@forelse($rows as $s)<tr><td>{{ $s->id }}</td><td>{{ $s->family?->head_name ?? '—' }} <small>{{ $s->family?->city }}</small></td><td>{{ $s->plan->name }} <small>({{ $s->plan->period }})</small></td><td>{{ $s->subscriber_type }}</td>
 <td><span class="badge {{ $s->status==='active'?'badge-ok':'' }}">{{ $s->status }}</span></td><td>{{ $s->starts_on?->format('d M Y') }}</td><td>{{ $s->ends_on?->format('d M Y') ?? '—' }}</td></tr>
@empty<tr><td colspan="7" class="text-center" style="color:var(--muted)">No subscriptions.</td></tr>@endforelse</tbody></table></div>{{ $rows->links() }}
@endsection
