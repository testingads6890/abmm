@extends('layouts.admin')
@section('title','Donations')
@section('content')
<div class="card p-4 mb-4 flex justify-between"><span>Total received (success)</span><b class="font-display text-2xl">₹{{ number_format($total) }}</b></div>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Date</th><th>Name</th><th>Mobile</th><th>Amount</th><th>Status / Payment ID</th></tr></thead><tbody>
@foreach($rows as $d)<tr><td data-l="Date">{{ $d->created_at?->format('d-M-Y') }}</td><td data-l="Name">{{ $d->name }}</td><td data-l="Mobile">{{ $d->mobile }}</td><td data-l="Amount">₹{{ number_format($d->amount) }}</td>
<td data-l="Status"><form method="post" action="{{ route('admin.donations.status',$d) }}" class="flex gap-1">@csrf @method('PATCH')<select name="status" class="input !min-h-[34px] !py-1 text-xs">@foreach(['pending','success','failed'] as $s)<option @selected($d->status===$s)>{{ $s }}</option>@endforeach</select><input name="payment_ref" value="{{ $d->payment_ref }}" class="input !min-h-[34px] !py-1 text-xs" placeholder="Payment ID"><button class="btn btn-ghost btn-sm">OK</button></form></td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $rows->links() }}</div>
@endsection
