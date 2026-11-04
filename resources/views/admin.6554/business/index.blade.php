@extends('layouts.admin')
@section('title','Businesses')
@section('content')
<div class="grid lg:grid-cols-3 gap-4"><div class="lg:col-span-2 grid gap-4 content-start">
<form class="card p-4 flex gap-3"><input class="input" name="q" value="{{ request('q') }}" placeholder="Name / type"><select class="input max-w-[160px]" name="status"><option value="">Status</option>@foreach(['pending','active','blocked'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select><button class="btn btn-ink">Go</button></form>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Business</th><th>Type</th><th>Contact</th><th>Reviews</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($rows as $b)<tr><td data-l="Business"><b>{{ $b->name }}</b><div class="text-xs" style="color:var(--muted)">{{ $b->address }}</div></td><td data-l="Type">{{ $b->type }}</td><td data-l="Contact">{{ $b->mobile }}</td><td data-l="Reviews">{{ $b->reviews_count }}</td>
<td data-l="Status"><form method="post" action="{{ route('admin.business.status',$b) }}" class="flex gap-1">@csrf @method('PATCH')<select name="status" class="input !min-h-[34px] !py-1 text-xs">@foreach(['pending','active','blocked'] as $s)<option @selected($b->status===$s)>{{ $s }}</option>@endforeach</select><button class="btn btn-ghost btn-sm">OK</button></form></td><td data-l="Action"><div class="acts"><a class="act act-ok" title="View / Edit" href="{{ route('admin.business.edit',$b) }}">🔍</a><a class="act act-info" title="Print" target="_blank" href="{{ route('admin.print',['business',$b->id]) }}?print=1">🖨</a></div></td></tr>@endforeach</tbody></table></div>{{ $rows->links() }}</div>
<form method="post" action="{{ route('admin.business.store') }}" class="card p-5 grid gap-3 content-start">@csrf<h3 class="font-display text-lg">Add business</h3>
 <input class="input" name="name" placeholder="Business name" required><input class="input" name="type" placeholder="Type" required><input class="input" name="address" placeholder="Address" required>
 <input class="input" name="mobile" maxlength="10" placeholder="Mobile" required><input class="input" type="email" name="email" placeholder="Email"><textarea class="input" name="description" rows="3" placeholder="Description"></textarea><button class="btn btn-primary">Save</button></form></div>
@endsection
