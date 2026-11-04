@extends('layouts.admin')
@section('title','Payment '.$p->number)
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<nav class="text-sm mb-3" style="color:var(--muted)"><a href="{{ route('admin.payments') }}">Payments</a> / <b>{{ $p->number }}</b></nav>
<div class="grid lg:grid-cols-2 gap-4">
<div class="card p-5 text-sm grid gap-1">
 <h3 class="font-display text-lg mb-2">Details</h3>
 @foreach([['Status',$p->status],['Purpose',$p->purpose.($p->purpose_ref?' #'.$p->purpose_ref:'')],['Payer',$p->payer_name.' · '.$p->payer_mobile.' · '.$p->payer_email],['Country / City',$p->country.' / '.$p->city],
  ['Amount',$p->currency.' '.number_format($p->amount,2)],['Tax included',number_format($p->tax_amount,2)],['Discount',number_format($p->discount,2).($p->coupon?' ('.$p->coupon.')':'')],['Refunded',number_format($p->refunded,2)],
  ['Gateway',$p->gateway],['Order id',$p->gateway_order_id],['Payment id',$p->gateway_payment_id],['Paid at',$p->paid_at],['Reconciled',$p->reconciled?'Yes':'No']] as [$k,$v])
 <div class="flex justify-between gap-3 border-b py-1" style="border-color:var(--line)"><span style="color:var(--muted)">{{ $k }}</span><span class="text-right break-all">{{ $v }}</span></div>@endforeach
</div>
<div class="grid gap-4 content-start">
 @if($p->receipt)<div class="card p-5 text-sm"><h3 class="font-display text-lg mb-2">Receipt {{ $p->receipt->number }}</h3>
  <p>Emailed: {{ $p->receipt->emailed_at?->format('d M H:i') ?? 'no' }} · WhatsApp: {{ $p->receipt->whatsapped_at?->format('d M H:i') ?? 'no' }}</p>
  <div class="flex gap-2 mt-2"><a class="btn btn-sm btn-ink" href="{{ route('admin.payments.receipt',$p) }}" target="_blank">Open PDF</a>
  <form method="post" action="{{ route('admin.payments.resend',$p) }}">@csrf<button class="btn btn-sm btn-ghost">Resend (app / mail / WhatsApp)</button></form></div></div>
 @elseif(in_array($p->status,['paid','partial_refund','refunded']))<div class="card p-5"><a class="btn btn-sm btn-ink" href="{{ route('admin.payments.receipt',$p) }}" target="_blank">Generate receipt PDF</a></div>@endif
 @if($p->gateway==='manual' && $p->status==='created')@can('payments.reconcile')<form method="post" action="{{ route('admin.payments.markpaid',$p) }}" class="card p-5 grid gap-2">@csrf<h3 class="font-display text-lg">Cash / bank payment received</h3>
  <input class="input" name="reference" placeholder="Cheque no / UTR / receipt book no" required><button class="btn btn-primary">Mark paid & send receipt</button></form>@endcan @endif
 @if(in_array($p->status,['paid','partial_refund']))@can('payments.refund')<form method="post" action="{{ route('admin.payments.refund',$p) }}" class="card p-5 grid gap-2" onsubmit="return confirm('Process the refund?')">@csrf<h3 class="font-display text-lg">Refund</h3>
  <input class="input" type="number" step="0.01" name="amount" value="{{ number_format($p->amount-$p->refunded,2,'.','') }}" required><input class="input" name="reason" placeholder="Reason" required><button class="btn btn-primary">Refund via {{ $p->gateway }}</button></form>@endcan @endif
 @if($p->refunds->count())<div class="card p-5 text-sm"><h3 class="font-display text-lg mb-2">Refunds</h3>@foreach($p->refunds as $r)<div>{{ $r->created_at->format('d M y') }} · {{ number_format($r->amount,2) }} · {{ $r->reason }} <small>{{ $r->gateway_refund_id }}</small></div>@endforeach</div>@endif
</div></div>
@endsection
