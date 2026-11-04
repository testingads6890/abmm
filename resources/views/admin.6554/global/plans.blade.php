@extends('layouts.admin')
@section('title','Plans & Coupons')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="flex gap-2 mb-3"><a class="btn btn-sm btn-ink" href="{{ route('admin.subscriptions') }}">Subscribers list →</a></div>
<h3 class="font-display text-lg mb-2">Plans</h3>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Code</th><th>Name</th><th>For</th><th>Period</th><th>Country</th><th>Cur</th><th>Price (tax incl.)</th><th>Features (1 per line)</th><th>Limit</th><th>On</th><th></th></tr></thead><tbody>
@foreach($plans as $p)<tr>
 <td><input class="input" form="p{{ $p->id }}" name="code" value="{{ $p->code }}" required></td><td><input class="input" form="p{{ $p->id }}" name="name" value="{{ $p->name }}" required></td>
 <td><select class="input" form="p{{ $p->id }}" name="audience">@foreach(\App\Models\Plan::AUDIENCES as $k=>$l)<option value="{{ $k }}" @selected($p->audience===$k)>{{ $l }}</option>@endforeach</select></td>
 <td><select class="input" form="p{{ $p->id }}" name="period">@foreach(array_keys(\App\Models\Plan::PERIODS) as $k)<option @selected($p->period===$k)>{{ $k }}</option>@endforeach</select></td>
 <td><input class="input !w-28" form="p{{ $p->id }}" name="country" value="{{ $p->country }}" placeholder="all"></td><td><input class="input !w-16" form="p{{ $p->id }}" name="currency" value="{{ $p->currency }}" maxlength="3"></td>
 <td><input class="input !w-28" form="p{{ $p->id }}" type="number" step="0.01" name="price" value="{{ $p->price }}" required></td>
 <td><textarea class="input" rows="2" form="p{{ $p->id }}" name="features">{{ implode("\n",$p->features ?? []) }}</textarea><input type="hidden" form="p{{ $p->id }}" name="description" value="{{ $p->description }}"></td>
 <td><input class="input !w-20" form="p{{ $p->id }}" type="number" name="max_items" value="{{ $p->max_items }}" placeholder="limit"></td><td><input type="checkbox" form="p{{ $p->id }}" name="is_active" value="1" @checked($p->is_active)></td>
 <td class="whitespace-nowrap"><form id="p{{ $p->id }}" method="post" action="{{ route('admin.plans.update',$p) }}" class="inline">@csrf @method('PUT')@can('plans.edit')<button class="btn btn-sm btn-ink">Save</button>@endcan</form>
  @can('plans.delete')<form method="post" action="{{ route('admin.plans.destroy',$p) }}" class="inline" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>@endforeach
</tbody></table></div>
@can('plans.create')
<form method="post" action="{{ route('admin.plans.store') }}" class="card p-5 grid sm:grid-cols-4 gap-3 mb-6">@csrf<h3 class="font-display text-lg col-span-full">New plan</h3>
 <input class="input" name="code" placeholder="code e.g. member-yearly" required><input class="input" name="name" placeholder="Plan name" required>
 <select class="input" name="audience">@foreach(\App\Models\Plan::AUDIENCES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
 <select class="input" name="period">@foreach(array_keys(\App\Models\Plan::PERIODS) as $k)<option>{{ $k }}</option>@endforeach</select>
 <input class="input" name="country" list="cl" placeholder="Country (blank = all)"><datalist id="cl">@foreach($countries as $c)<option value="{{ $c }}">@endforeach</datalist>
 <input class="input" name="currency" value="INR" maxlength="3"><input class="input" type="number" step="0.01" name="price" placeholder="Price (tax included)" required><input class="input" type="number" name="sort" placeholder="Order"><input class="input" type="number" name="max_items" placeholder="Listing limit (profiles / jobs / events per period)">
 <textarea class="input sm:col-span-4" name="features" rows="3" placeholder="Features, ek line me ek"></textarea><input type="hidden" name="is_active" value="1">
 <div class="col-span-full"><button class="btn btn-primary">Create plan</button></div></form>@endcan

<h3 class="font-display text-lg mb-2">Coupons</h3>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Code</th><th>Discount</th><th>Used</th><th>Valid</th><th>For</th><th>Active</th></tr></thead><tbody>
@forelse($coupons as $c)<tr><td>{{ $c->code }}</td><td>{{ $c->type==='percent' ? $c->value.'%' : $c->value }}</td><td>{{ $c->used }}{{ $c->max_uses ? ' / '.$c->max_uses : '' }}</td><td>{{ $c->valid_from?->format('d M y') }} – {{ $c->valid_to?->format('d M y') }}</td><td>{{ $c->audience ?: 'all' }}</td>
 <td>@can('plans.edit')<form method="post" action="{{ route('admin.coupons.toggle',$c) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $c->is_active ? 'Yes':'No' }}</button></form>@endcan</td></tr>
@empty<tr><td colspan="6" class="text-center" style="color:var(--muted)">No coupons.</td></tr>@endforelse</tbody></table></div>
@can('plans.create')
<form method="post" action="{{ route('admin.coupons.store') }}" class="card p-5 grid sm:grid-cols-4 gap-3">@csrf<h3 class="font-display text-lg col-span-full">New coupon</h3>
 <input class="input" name="code" placeholder="DIWALI25" required><select class="input" name="type"><option value="percent">Percent %</option><option value="flat">Flat amount</option></select><input class="input" type="number" step="0.01" name="value" placeholder="Value" required><input class="input" type="number" name="max_uses" placeholder="Max uses">
 <input class="input" type="date" name="valid_from"><input class="input" type="date" name="valid_to"><select class="input" name="audience"><option value="">All plans</option>@foreach(\App\Models\Plan::AUDIENCES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
 <button class="btn btn-primary">Create</button></form>@endcan
@endsection
