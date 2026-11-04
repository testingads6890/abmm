@extends('layouts.admin')
@section('title','Rewards & Redemptions')
@section('content')
@can('points.edit')<form method="post" action="{{ route('admin.rewards.store') }}" class="card p-3 flex flex-wrap gap-2 mb-4 items-end">@csrf
 <input class="input !w-56" name="name" placeholder="Reward name" required><input class="input !w-64" name="description" placeholder="Description"><input class="input !w-28" type="number" name="cost" placeholder="Points" required><input class="input !w-28" type="number" name="stock" placeholder="Stock (blank=∞)"><label class="text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label><button class="btn btn-sm btn-ink">Add reward</button></form>@endcan
<div class="grid lg:grid-cols-2 gap-4">
<div class="card p-3 overflow-x-auto"><h3 class="font-display text-lg mb-2">Catalogue</h3><table class="tbl"><thead><tr><th>Reward</th><th>Cost</th><th>Stock</th><th>Active</th></tr></thead><tbody>@foreach($rewards as $w)<tr><td>{{ $w->name }}</td><td>{{ $w->cost }}</td><td>{{ $w->stock ?? '∞' }}</td><td>{{ $w->is_active ? 'Yes':'No' }}</td></tr>@endforeach</tbody></table></div>
<div class="card p-3 overflow-x-auto"><h3 class="font-display text-lg mb-2">Redemption requests</h3><table class="tbl"><thead><tr><th>Member</th><th>Reward</th><th>Pts</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($reds as $d)<tr><td>{{ $d->name }}<div class="text-xs">{{ $d->mobile }}</div></td><td>{{ $d->reward }}</td><td>{{ $d->points }}</td><td>{{ $d->status }}</td><td>
 @if($d->status==='pending')@can('points.redeem')<form method="post" action="{{ route('admin.redemptions.decide',$d->id) }}" class="flex gap-1">@csrf<input class="input !w-28" name="remark" placeholder="Remark"><button name="decision" value="fulfilled" class="btn btn-sm btn-ink">Fulfil</button><button name="decision" value="rejected" class="btn btn-sm">Reject</button></form>@endcan @endif</td></tr>@endforeach</tbody></table></div></div>
@endsection
