@extends('layouts.admin')
@section('title','Trust Submissions')
@section('content')
@if($errors->any())<div class="card p-3 mb-3 text-sm" style="border-color:#c0392b">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form class="card p-4 flex flex-wrap gap-3 mb-3"><select class="input" name="status" style="max-width:14rem"><option value="">All status</option>@foreach(['submitted'=>'Submitted','pending'=>'Payment pending','failed'=>'Failed'] as $k=>$l)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $l }}</option>@endforeach</select><button class="btn btn-ink">Filter</button></form>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>#</th><th>Trust</th><th>Govt. State / District</th><th>Department</th><th>Fee</th><th>Payment ID</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $s)<tr><td data-l="#">{{ $s->id }}</td><td data-l="Trust"><b>{{ $s->trust->trust_name }}</b><div class="text-xs" style="color:var(--muted)">{{ $s->trust->trust_category }} · {{ $s->trust->name }}</div></td><td data-l="State">{{ $s->trust->state }} / {{ $s->trust->district }}</td>
 <td data-l="Department">{{ $s->department->name }}<div class="text-xs" style="color:var(--muted)">{{ $s->department->email }}</div></td><td data-l="Fee">{{ $s->amount>0 ? '₹'.number_format($s->amount) : 'Free' }}</td><td data-l="Payment ID" class="text-xs">{{ $s->razorpay_payment_id }}</td>
 <td data-l="Status"><span class="badge {{ $s->status==='submitted'?'badge-ok':($s->status==='failed'?'badge-warn':'') }}">{{ ucfirst($s->status) }}</span>@if($s->mail_error)<div class="text-xs" style="color:#c0392b">Mail fail: {{ $s->mail_error }}</div>@endif</td>
 <td data-l="Date" class="text-xs">{{ ($s->submitted_at ?? $s->created_at)->format('d-M-Y h:i A') }}</td>
 <td>@if($s->status==='submitted')@can('trusts.submit')<form method="post" action="{{ route('admin.trusts.resend',$s) }}">@csrf<button class="act act-info" title="Resend e-mail to department">✉</button></form>@endcan @endif</td></tr>
@empty<tr><td colspan="9" class="py-6" style="color:var(--muted)">No submissions.</td></tr>@endforelse</tbody></table></div>
<div class="my-4">{{ $rows->links() }}</div>
@endsection
