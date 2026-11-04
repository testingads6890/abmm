@extends('layouts.admin')
@section('title','Payments ledger')
@section('content')
<form method="get" class="flex flex-wrap gap-2 mb-3">
 <input class="input !w-56" name="q" value="{{ request('q') }}" placeholder="Payment no / name / mobile / gateway ref">
 <select class="input !w-36" name="status"><option value="">Status</option>@foreach(['created','paid','failed','partial_refund','refunded'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
 <select class="input !w-36" name="purpose"><option value="">Purpose</option>@foreach(['subscription','form','donation','event','ad','other'] as $s)<option @selected(request('purpose')===$s)>{{ $s }}</option>@endforeach</select>
 <select class="input !w-32" name="gateway"><option value="">Gateway</option>@foreach(['razorpay','stripe','manual'] as $s)<option @selected(request('gateway')===$s)>{{ $s }}</option>@endforeach</select>
 <input class="input !w-36" name="country" value="{{ request('country') }}" placeholder="Country"><input class="input !w-36" type="date" name="from" value="{{ request('from') }}"><input class="input !w-36" type="date" name="to" value="{{ request('to') }}">
 <button class="btn btn-sm btn-ink">Filter</button>
 @can('payments.export')<a class="btn btn-sm btn-ghost" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx']) }}">Excel</a><a class="btn btn-sm btn-ghost" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}">CSV</a>@endcan
</form>
@can('payments.reconcile')<form method="post" action="{{ route('admin.payments.reconcile') }}" class="mb-3" onsubmit="return confirm('Check pending payments with the gateway?')">@csrf<button class="btn btn-sm btn-ghost">Reconcile now</button></form>@endcan
<div class="flex flex-wrap gap-3 mb-3">@foreach($totals as $t)<div class="card px-4 py-2 text-sm"><b>{{ $t->currency }} {{ number_format($t->a - $t->r,2) }}</b> net <span style="color:var(--muted)">· {{ $t->c }} payments · refunded {{ number_format($t->r,2) }}</span></div>@endforeach</div>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>No.</th><th>Date</th><th>Payer</th><th>Purpose</th><th>Country</th><th>Amount</th><th>Gateway</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($rows as $p)<tr><td><a href="{{ route('admin.payments.show',$p) }}">{{ $p->number }}</a></td><td>{{ $p->created_at->format('d M y H:i') }}</td><td>{{ $p->payer_name }}<br><small>{{ $p->payer_mobile }}</small></td><td>{{ $p->purpose }}</td><td>{{ $p->country }}</td>
 <td>{{ $p->currency }} {{ number_format($p->amount,2) }}@if($p->refunded>0)<br><small>refund {{ number_format($p->refunded,2) }}</small>@endif</td><td>{{ $p->gateway }}{{ $p->reconciled ? ' ✓' : '' }}</td>
 <td><span class="badge {{ $p->status==='paid'?'badge-ok':'' }}">{{ $p->status }}</span></td><td><a class="btn btn-sm btn-ghost" href="{{ route('admin.payments.show',$p) }}">Open</a></td></tr>
@empty<tr><td colspan="9" class="text-center" style="color:var(--muted)">No payments.</td></tr>@endforelse</tbody></table></div>{{ $rows->links() }}
@endsection
