@extends('layouts.admin')
@section('title', $cfg['label'].' · Master Data')
@section('content')
@php $fixed = ! empty($cfg['fixed']); $hasParent = isset($cfg['parent']); $hasPin = ! empty($cfg['pincode']); $hasDial = ! empty($cfg['dial']); @endphp
<div class="flex flex-wrap justify-between items-center gap-3 mb-3">
  <nav class="text-sm" style="color:var(--muted)">Dashboard / Master Data / <b>{{ $cfg['label'] }}</b> <span class="badge">{{ number_format($total) }}</span></nav>
</div>
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@if(! empty($cfg['help']))<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fff7e6">{{ $cfg['help'] }}</div>@endif
@if($fixed)<div class="mb-4 p-3 rounded-xl text-sm badge-ok">This is a fixed system list (Yes / No) and cannot be changed.</div>@endif

@can('masters.create')@unless($fixed)
<details class="card p-4 mb-4" {{ $errors->any() ? 'open' : '' }}>
  <summary class="cursor-pointer font-semibold">+ Add new value</summary>
  <form method="post" action="{{ route('admin.masters.store',$type) }}" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">@csrf
    @if($hasParent)<div><label class="label">{{ $parentLabel }} *</label><select class="input" name="parent_id" required><option value="">- choose -</option>@foreach($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id')==$p->id)>{{ $p->name }}</option>@endforeach</select></div>@endif
    <div><label class="label">{{ $cfg['label'] }} (ek)</label><input class="input" name="name" value="{{ old('name') }}" maxlength="190"></div>
    @if($hasPin)<div><label class="label">Pincode (6 digit)</label><input class="input" name="pincode" value="{{ old('pincode') }}" maxlength="6" inputmode="numeric"><small style="color:var(--muted)">Family form me tehsil chunte hi automatic bharega.</small></div>@endif
    @if($hasDial)<div><label class="label">Phone country code</label><input class="input" name="dial" value="{{ old('dial') }}" maxlength="5" placeholder="+91"><small style="color:var(--muted)">It will appear in the dropdown with the mobile number.</small></div>@endif
    <div class="sm:col-span-2 lg:col-span-4"><label class="label">Or add many at once (one per line{{ $hasPin ? ', "Name,pincode" also works' : '' }}{{ $hasDial ? ', "Name,+91" also works' : '' }})</label><textarea class="input" rows="4" name="bulk" placeholder="Value 1&#10;Value 2">{{ old('bulk') }}</textarea></div>
    <div><button class="btn btn-primary">Add</button></div>
  </form>
</details>
@endunless @endcan

<form class="card p-4 grid sm:grid-cols-4 gap-3 mb-4">
  <input class="input sm:col-span-2" name="q" value="{{ request('q') }}" placeholder="Search {{ $cfg['label'] }}">
  @if($hasParent)<select class="input" name="parent"><option value="">All {{ $parentLabel }}</option>@foreach($parents as $p)<option value="{{ $p->id }}" @selected(request('parent')==$p->id)>{{ $p->name }}</option>@endforeach</select>@endif
  <select class="input" name="status"><option value="">Active + Inactive</option><option value="inactive" @selected(request('status')==='inactive')>Only inactive</option></select>
  <div class="flex gap-2"><button class="btn btn-ink flex-1">Search</button><a class="btn btn-ghost" href="{{ route('admin.masters.index',$type) }}">Reset</a></div>
</form>

<div class="card overflow-x-auto"><table class="tbl w-full text-sm"><thead><tr><th>#</th><th>Name</th>@if($hasParent)<th>{{ $parentLabel }}</th>@endif @if($hasPin)<th>Pincode</th>@endif @if($hasDial)<th>Phone code</th>@endif<th>Order</th><th>Active</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $i=>$row)
<tr>
 <td data-l="#">{{ $rows->firstItem()+$i }}</td>
 <td data-l="Name"><input form="m{{ $row->id }}" class="input" name="name" value="{{ $row->name }}" maxlength="190" required {{ $fixed ? 'readonly' : '' }}></td>
 @if($hasParent)<td data-l="{{ $parentLabel }}"><select form="m{{ $row->id }}" class="input" name="parent_id" {{ $fixed ? 'disabled' : '' }}>@foreach($parents as $p)<option value="{{ $p->id }}" @selected($row->parent_id==$p->id)>{{ $p->name }}</option>@endforeach</select></td>@endif
 @if($hasPin)<td data-l="Pincode"><input form="m{{ $row->id }}" class="input" name="pincode" value="{{ $row->pincode }}" maxlength="6" inputmode="numeric" style="max-width:110px"></td>@endif
 @if($hasDial)<td data-l="Phone code"><input form="m{{ $row->id }}" class="input" name="dial" value="{{ $row->meta['dial'] ?? '' }}" maxlength="5" placeholder="+91" style="max-width:90px"></td>@endif
 <td data-l="Order"><input form="m{{ $row->id }}" class="input" type="number" name="sort" value="{{ $row->sort }}" style="max-width:90px" {{ $fixed ? 'readonly' : '' }}></td>
 <td data-l="Active"><input form="m{{ $row->id }}" type="checkbox" name="is_active" value="1" @checked($row->is_active) {{ $fixed ? 'disabled' : '' }}></td>
 <td data-l="Action"><div class="flex gap-1">
   @can('masters.edit')@unless($fixed)<form id="m{{ $row->id }}" method="post" action="{{ route('admin.masters.update',$row) }}">@csrf @method('PUT')<button class="btn btn-ghost btn-sm">Save</button></form>@endunless @endcan
   @can('masters.delete')@unless($fixed)<form method="post" action="{{ route('admin.masters.destroy',$row) }}" onsubmit="return confirm('Remove? (It will not be removed if it is in use)')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form>@endunless @endcan
 </div></td>
</tr>
@if($type==='trust_name')
<tr><td></td><td colspan="6" class="!pt-0"><details><summary class="text-xs cursor-pointer" style="color:var(--muted)">App directory details (address, e-mail, contact, PDF link) - saved with the "Save" button</summary>
 <div class="grid sm:grid-cols-3 gap-2 mt-2">
  <input form="m{{ $row->id }}" class="input sm:col-span-3" name="meta[address]" value="{{ $row->meta['address'] ?? '' }}" placeholder="Address">
  <input form="m{{ $row->id }}" class="input" name="meta[country]" value="{{ $row->meta['country'] ?? 'INDIA' }}" placeholder="Country"><input form="m{{ $row->id }}" class="input" name="meta[state]" value="{{ $row->meta['state'] ?? '' }}" placeholder="Govt. State">
  <input form="m{{ $row->id }}" class="input" type="email" name="meta[email]" value="{{ $row->meta['email'] ?? '' }}" placeholder="Email ID"><input form="m{{ $row->id }}" class="input" name="meta[contact]" value="{{ $row->meta['contact'] ?? '' }}" placeholder="Contact number">
  <input form="m{{ $row->id }}" class="input sm:col-span-2" type="url" name="meta[pdf_url]" value="{{ $row->meta['pdf_url'] ?? '' }}" placeholder="Download PDF link (https://...)"></div></details></td></tr>
@endif
@empty<tr><td colspan="7" class="text-center py-8" style="color:var(--muted)">No values.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
