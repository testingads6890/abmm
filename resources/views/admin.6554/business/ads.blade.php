@extends('layouts.admin')
@section('title','Business Advertisements')
@section('content')
<div class="grid lg:grid-cols-3 gap-4"><div class="card p-2 lg:col-span-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Business</th><th>Title</th><th>Period</th><th>Amount</th><th>Status</th></tr></thead><tbody>
@foreach($rows as $a)<tr><td data-l="Business">{{ $a->business?->name }}</td><td data-l="Title">{{ $a->title }}</td><td data-l="Period">{{ $a->starts_on->format('d M') }} – {{ $a->ends_on->format('d M Y') }}</td><td data-l="Amount">₹{{ number_format($a->amount) }}</td><td data-l="Status"><form method="post" action="{{ route('admin.business.ads.status',$a) }}" class="flex gap-1">@csrf @method('PATCH')<select name="status" class="input !min-h-[34px] !py-1 text-xs">@foreach(['pending','approved','rejected'] as $s)<option @selected($a->status===$s)>{{ $s }}</option>@endforeach</select><button class="btn btn-ghost btn-sm">OK</button></form></td></tr>@endforeach</tbody></table></div>
<form method="post" action="{{ route('admin.business.ads.store') }}" class="card p-5 grid gap-3 content-start">@csrf<h3 class="font-display text-lg">Add advertisement</h3>
<select class="input" name="business_id" required><option value="">Business</option>@foreach($businesses as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
<input class="input" name="title" placeholder="Title" required><div class="grid grid-cols-2 gap-2"><input class="input" type="date" name="starts_on" required><input class="input" type="date" name="ends_on" required></div>
<input class="input" type="number" step="0.01" name="amount" placeholder="Amount ₹" required><input class="input" name="payment_ref" placeholder="Payment ID"><input class="input" name="location" placeholder="Location"><input class="input" name="contact_person" placeholder="Contact person"><input class="input" name="contact_mobile" maxlength="10" placeholder="Contact mobile"><button class="btn btn-primary">Save</button></form></div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
