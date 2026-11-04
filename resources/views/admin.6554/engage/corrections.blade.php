@extends('layouts.admin')
@section('title','Data correction requests')
@section('content')
<form method="get" class="mb-3"><select class="input !w-44" name="status" onchange="this.form.submit()">@foreach(['pending','approved','rejected'] as $s)<option @selected($status===$s)>{{ $s }}</option>@endforeach</select></form>
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Family</th><th>By</th><th>Field</th><th>Old → New</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($rows as $c)<tr><td>{{ $c->head_name }}<div class="text-xs">{{ $c->city }}</div></td><td>{{ $c->requester }}</td><td>{{ $c->target }}.{{ $c->field }}</td><td>{{ $c->old_value ?: '—' }} → <b>{{ $c->new_value }}</b></td><td class="text-xs">{{ $c->reason }}</td><td>{{ $c->status }}</td>
<td>@if($c->status==='pending')@can('corrections.decide')<form method="post" action="{{ route('admin.corrections.decide',$c->id) }}" class="flex gap-1">@csrf<input class="input !w-28" name="remark" placeholder="Remark"><button name="decision" value="approved" class="btn btn-sm btn-ink">Approve</button><button name="decision" value="rejected" class="btn btn-sm">Reject</button></form>@endcan @else {{ $c->remark }} @endif</td></tr>
@empty<tr><td colspan="7" style="color:var(--muted)">No requests.</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div>
@endsection
