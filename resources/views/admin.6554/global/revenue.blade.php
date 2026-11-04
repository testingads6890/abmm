@extends('layouts.admin')
@section('title','Revenue')
@section('content')
<form method="get" class="flex flex-wrap gap-2 mb-4"><input class="input !w-40" type="date" name="from" value="{{ $from }}"><input class="input !w-40" type="date" name="to" value="{{ $to }}"><button class="btn btn-sm btn-ink">Apply</button></form>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
 @forelse($byCurrency as $c)<div class="card p-4"><div class="text-xs" style="color:var(--muted)">Net collection ({{ $c->currency }})</div><div class="text-2xl font-display">{{ number_format($c->gross-$c->refunded,2) }}</div>
  <div class="text-xs" style="color:var(--muted)">{{ $c->c }} payments · tax {{ number_format($c->tax,2) }} · discounts {{ number_format($c->discount,2) }} · refunds {{ number_format($c->refunded,2) }}</div></div>
 @empty<div class="card p-4 text-sm" style="color:var(--muted)">No payments in this period.</div>@endforelse
 <div class="card p-4"><div class="text-xs" style="color:var(--muted)">Plans expiring in 30 days</div><div class="text-2xl font-display">{{ $expiring }}</div></div>
 <div class="card p-4"><div class="text-xs" style="color:var(--muted)">Pending (unpaid) payments</div><div class="text-2xl font-display">{{ $pending }}</div></div>
</div>
<div class="grid lg:grid-cols-2 gap-4">
 <div class="card p-3 overflow-x-auto"><h3 class="font-display text-lg mb-2">By country</h3><table class="tbl"><thead><tr><th>Country</th><th>Cur</th><th>Payments</th><th>Net</th></tr></thead><tbody>@foreach($byCountry as $x)<tr><td>{{ $x->country ?: '—' }}</td><td>{{ $x->currency }}</td><td>{{ $x->c }}</td><td>{{ number_format($x->net,2) }}</td></tr>@endforeach</tbody></table></div>
 <div class="card p-3 overflow-x-auto"><h3 class="font-display text-lg mb-2">By source</h3><table class="tbl"><thead><tr><th>Source</th><th>Cur</th><th>Payments</th><th>Net</th></tr></thead><tbody>@foreach($byPurpose as $x)<tr><td>{{ ucfirst($x->purpose) }}</td><td>{{ $x->currency }}</td><td>{{ $x->c }}</td><td>{{ number_format($x->net,2) }}</td></tr>@endforeach</tbody></table></div>
 <div class="card p-3 overflow-x-auto"><h3 class="font-display text-lg mb-2">By month</h3><table class="tbl"><thead><tr><th>Month</th><th>Cur</th><th>Net</th></tr></thead><tbody>@foreach($byMonth as $x)<tr><td>{{ $x->m }}</td><td>{{ $x->currency }}</td><td>{{ number_format($x->net,2) }}</td></tr>@endforeach</tbody></table></div>
 <div class="card p-3 overflow-x-auto"><h3 class="font-display text-lg mb-2">Subscribers</h3><table class="tbl"><thead><tr><th>Type</th><th>Status</th><th>Count</th></tr></thead><tbody>@foreach($subs as $x)<tr><td>{{ $x->subscriber_type }}</td><td>{{ $x->status }}</td><td>{{ $x->c }}</td></tr>@endforeach</tbody></table></div>
</div>
@endsection
