@extends('layouts.admin')
@section('title','Trust Submission Payment')
@section('content')
<div class="max-w-md mx-auto text-center"><div class="card p-8"><div class="stat-num">₹{{ number_format($sub->amount) }}</div>
 <p class="mt-2" style="color:var(--muted)">{{ $sub->trust->trust_name }} ({{ $sub->trust->name }}) → {{ $sub->department->name }}</p>
 <button id="payBtn" class="btn btn-primary w-full mt-6">Pay with Razorpay</button>
 <p class="text-xs mt-3" style="color:var(--muted)">The Trust is submitted to the department as soon as payment is verified.</p></div>
 <form id="vf" method="post" action="{{ route('admin.trusts.verify') }}">@csrf<input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature"></form></div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const f=document.getElementById('vf');
const rzp=new Razorpay({key:@json($keyId),amount:@json($order['amount']),currency:'INR',order_id:@json($order['id']),name:'ABMM Sampark',description:'Trust submission',
 prefill:{name:@json($sub->trust->name),email:@json($sub->trust->email),contact:@json($sub->trust->contact)},theme:{color:'#c62828'},
 handler:r=>{f.razorpay_order_id.value=r.razorpay_order_id;f.razorpay_payment_id.value=r.razorpay_payment_id;f.razorpay_signature.value=r.razorpay_signature;f.submit()}});
document.getElementById('payBtn').onclick=()=>rzp.open(); rzp.open();
</script>
@endsection
