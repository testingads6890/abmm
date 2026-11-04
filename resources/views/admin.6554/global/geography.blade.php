@extends('layouts.admin')
@section('title','Global Geography')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<p class="text-sm mb-3" style="color:var(--muted)">Structure: <b>Country → State → District → City → Zone 1, Zone 2 …</b>. Country/State/District/City master data is in the "Master Data" menu; here you manage country-wise currency, gateway, tax and city-wise zones.</p>

<h3 class="font-display text-lg mb-2">Countries</h3>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Country</th><th>ISO</th><th>Dial</th><th>Currency</th><th>Gateway</th><th>Tax</th><th>Lang</th><th>Timezone</th><th>Active</th></tr></thead><tbody>
@forelse($countries as $c)<tr><td>{{ $c->country }}</td><td>{{ $c->iso }}</td><td>{{ $c->dial_code }}</td><td>{{ $c->currency }}</td><td>{{ $c->gateway }}</td><td>{{ $c->tax_name }} {{ $c->tax_rate }}%</td><td>{{ $c->language }}</td><td>{{ $c->timezone }}</td>
 <td>@if(auth()->user()->isSuper())<form method="post" action="{{ route('admin.geography.country.toggle',$c) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $c->is_active ? 'Yes' : 'No' }}</button></form>@else{{ $c->is_active ? 'Yes':'No' }}@endif</td></tr>
@empty<tr><td colspan="9" class="text-center" style="color:var(--muted)">No country is configured yet (default India: INR / Razorpay / GST 18%).</td></tr>@endforelse
</tbody></table></div>
@if(auth()->user()->isSuper())@can('geography.edit')
<form method="post" action="{{ route('admin.geography.country') }}" class="card p-5 grid sm:grid-cols-4 gap-3 mb-6">@csrf<h3 class="font-display text-lg col-span-full">Country add / update</h3>
 <div><label class="label">Country</label><input class="input" name="country" list="mc" required placeholder="USA"><datalist id="mc">@foreach($masterCountries as $m)<option value="{{ $m }}">@endforeach</datalist></div>
 <div><label class="label">ISO (2-3)</label><input class="input" name="iso" maxlength="3" placeholder="US"></div>
 <div><label class="label">Dial code</label><input class="input" name="dial_code" value="+1" required></div>
 <div><label class="label">Currency</label><input class="input" name="currency" value="USD" maxlength="3" required></div>
 <div><label class="label">Gateway</label><select class="input" name="gateway"><option value="razorpay">Razorpay (India)</option><option value="stripe" selected>Stripe (international)</option><option value="manual">Manual / bank</option></select></div>
 <div><label class="label">Tax name</label><input class="input" name="tax_name" placeholder="VAT / GST"></div>
 <div><label class="label">Tax % (price me included)</label><input class="input" type="number" step="0.01" name="tax_rate" value="0" required></div>
 <div><label class="label">Default language</label><input class="input" name="language" value="en" maxlength="5" required></div>
 <div><label class="label">Timezone</label><input class="input" name="timezone" value="America/New_York" required></div>
 <label class="text-sm flex items-end gap-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
 <div class="col-span-full"><button class="btn btn-primary">Save country</button></div></form>
@endcan @endif

<h3 class="font-display text-lg mb-2">City zones</h3>
<form method="get" class="flex flex-wrap gap-2 mb-3"><input class="input !w-48" name="country" value="{{ request('country') }}" placeholder="Country"><input class="input !w-48" name="city" value="{{ request('city') }}" placeholder="City"><button class="btn btn-sm btn-ink">Filter</button></form>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Country</th><th>State</th><th>District</th><th>City</th><th>Zone</th><th>Active</th><th></th></tr></thead><tbody>
@forelse($zones as $z)<tr><td>{{ $z->country }}</td><td>{{ $z->state }}</td><td>{{ $z->district }}</td><td>{{ $z->city }}</td><td>{{ $z->name }}</td>
 <td>@can('geography.edit')<form method="post" action="{{ route('admin.zones.toggle',$z) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $z->is_active ? 'Yes':'No' }}</button></form>@endcan</td>
 <td>@can('geography.delete')<form method="post" action="{{ route('admin.zones.destroy',$z) }}" onsubmit="return confirm('Remove the zone?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>
@empty<tr><td colspan="7" class="text-center" style="color:var(--muted)">No zones.</td></tr>@endforelse
</tbody></table></div>{{ $zones->links() }}
@can('geography.create')
<div class="grid lg:grid-cols-2 gap-4 mt-4">
<form method="post" action="{{ route('admin.zones.store') }}" class="card p-5 grid sm:grid-cols-2 gap-3">@csrf<h3 class="font-display text-lg col-span-full">Add a zone</h3>
 <input class="input" name="country" placeholder="Country" value="{{ auth()->user()->country }}" required><input class="input" name="state" placeholder="State"><input class="input" name="district" placeholder="District"><input class="input" name="city" placeholder="City" value="{{ auth()->user()->city }}" required>
 <input class="input" name="name" placeholder="Zone 1 / North Zone" required><input class="input" name="code" placeholder="Code (optional)"><div class="col-span-full"><button class="btn btn-primary">Add zone</button></div></form>
<form method="post" action="{{ route('admin.zones.import') }}" class="card p-5 grid gap-3">@csrf<h3 class="font-display text-lg">Bulk import</h3>
 <p class="text-xs" style="color:var(--muted)">Har line: <code>Country | State | District | City | Zone</code>. Instead of a zone <code>#5</code> to create Zone 1…Zone 5 automatically.<br>Example: <code>INDIA | Maharashtra | Nagpur | Nagpur | #6</code></p>
 <textarea class="input" rows="6" name="bulk" required></textarea><div><button class="btn btn-primary">Import</button></div></form>
</div>@endcan
@endsection
