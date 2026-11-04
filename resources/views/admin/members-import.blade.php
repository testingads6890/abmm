@extends('layouts.admin')
@section('title','Import / Export Members')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@php $res = session('import_result'); @endphp

<p class="text-sm mb-4" style="color:var(--muted)">Members can come in three ways: the <b>member registers himself</b> in the app, an admin adds a family <b>manually one by one</b>, or an admin uploads a <b>CSV of many people at once</b> (below). Everything can also be downloaded as CSV / Excel.</p>

<div class="grid sm:grid-cols-3 gap-3 mb-5">
 <div class="card p-4 text-sm"><b>1. Self registration</b><p class="mt-1" style="color:var(--muted)">The member registers in the app with his own mobile. His family appears in the Member list (marked "app") and can be verified.</p></div>
 <div class="card p-4 text-sm"><b>2. Add manually (single)</b><p class="mt-1" style="color:var(--muted)">Add one family with the form, then add each member on the family page.</p>@can('families.create')<a class="btn btn-ghost btn-sm mt-2" href="{{ route('admin.families.create') }}">+ Add New Family</a>@endcan</div>
 <div class="card p-4 text-sm"><b>3. Import CSV (bulk)</b><p class="mt-1" style="color:var(--muted)">One row per person, grouped by Family ID. The <b>HOF</b> row becomes the family head, all other rows become its members.</p></div>
</div>

@if($res)
<div class="card p-5 mb-5 text-sm">
 <h3 class="font-display text-lg mb-2">@if($res['dry'])Check result (dry run) &mdash; nothing was saved @else Import finished (batch #{{ $res['batch'] }}) @endif</h3>
 <div class="flex flex-wrap gap-2 mb-3">
  @foreach(['rows'=>'Rows read','families_created'=>'Families new','families_updated'=>'Families updated','members_created'=>'Members new','members_updated'=>'Members updated','skipped'=>'Rows skipped'] as $k=>$l)
   <span class="card px-3 py-1">{{ $l }}: <b>{{ $res['stats'][$k] }}</b></span>@endforeach
 </div>
 @php $c = $res['counters']; @endphp
 <ul class="list-disc pl-5 mb-3" style="color:var(--muted)">
  @if($c['no_mobile'])<li>{{ $c['no_mobile'] }} people have no mobile number (they are saved, but cannot log in to the app until a mobile is added).</li>@endif
  @if($c['no_head'])<li>{{ $c['no_head'] }} families had no HOF row; one person was used as head.</li>@endif
  @if($c['bad_date'])<li>{{ $c['bad_date'] }} dates could not be read and were left empty.</li>@endif
  @if($c['bad_pincode'])<li>{{ $c['bad_pincode'] }} pincodes were not 6 digits and were left empty.</li>@endif
  @if($c['bad_mobile'])<li>{{ $c['bad_mobile'] }} mobile numbers were invalid and were left empty.</li>@endif
  @if($c['bad_blood'])<li>{{ $c['bad_blood'] }} blood groups were "Don't know"/invalid and were left empty.</li>@endif
  @if($c['no_relation'])<li>{{ $c['no_relation'] }} rows had no usable Relationship; saved as OTHER.</li>@endif
  @if($c['duplicate_row'])<li>{{ $c['duplicate_row'] }} names appear more than once inside a family (kept as separate people - please check for double entries).</li>@endif
 </ul>
 @if($res['sample'])<div class="mb-3"><b>Preview:</b><table class="tbl mt-1"><thead><tr><th>Family ID</th><th>Head</th><th>Place</th><th>Other members</th><th></th></tr></thead><tbody>
  @foreach($res['sample'] as $s)<tr><td>{{ $s['code'] }}</td><td>{{ $s['head'] }}</td><td>{{ $s['place'] }}</td><td>{{ $s['members'] }}</td><td>{{ $s['new'] ? 'new' : 'update' }}</td></tr>@endforeach</tbody></table></div>@endif
 @if($res['errors'])<div class="p-3 rounded-xl badge-warn max-h-56 overflow-auto mb-2"><b>Skipped rows ({{ count($res['errors']) }}):</b><ul class="list-disc pl-5">@foreach($res['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
 @if($res['notes'])<details><summary class="cursor-pointer">Warnings ({{ count($res['notes']) }})</summary><ul class="list-disc pl-5 max-h-56 overflow-auto">@foreach($res['notes'] as $e)<li>{{ $e }}</li>@endforeach</ul></details>@endif
 @if($res['dry'])<p class="mt-2">Looks right? Choose the same file again, <b>untick</b> "Dry run" and press Import.</p>@endif
</div>
@endif

<div class="grid lg:grid-cols-3 gap-4">
 @if($canImport)
 <form method="post" enctype="multipart/form-data" action="{{ route('admin.members.import.store') }}" class="card p-5 grid gap-3 content-start lg:col-span-1">@csrf
  <h3 class="font-display text-lg">Import members (CSV)</h3>
  <input class="input" type="file" name="file" accept=".csv,.txt" required>
  <p class="text-xs" style="color:var(--muted)">Used only where the sheet has no value (optional):</p>
  <div class="grid grid-cols-2 gap-2">
   <input class="input" name="country" value="{{ old('country','INDIA') }}" placeholder="Country">
   <input class="input" name="state" value="{{ old('state') }}" placeholder="State">
   <input class="input" name="pradesh" value="{{ old('pradesh') }}" placeholder="ABMM Pradesh">
   <input class="input" name="city" value="{{ old('city') }}" placeholder="City">
   <input class="input col-span-2" name="zone" value="{{ old('zone') }}" placeholder="Zone (e.g. Zone 1)">
  </div>
  <label class="flex gap-2 text-sm"><input type="checkbox" name="dry_run" value="1" checked class="accent-orange-600"> Dry run first (nothing will be saved)</label>
  <button class="btn btn-primary">Import</button>
  <a class="underline text-sm" href="{{ route('admin.members.import.sample') }}">Download sample CSV</a>
  @unless(auth()->user()->isSuper())<p class="text-xs" style="color:var(--muted)">You can import only for your own area. Other rows are skipped.</p>@endunless
 </form>
 @endif

 <div class="card p-5 lg:col-span-2 text-sm grid gap-2 content-start">
  <h3 class="font-display text-lg">CSV format</h3>
  <p>Columns (any order; names are matched loosely): <span style="color:var(--muted)">{{ implode(' · ', array_slice($headers, 0, 13)) }} …</span></p>
  <p><b>Required:</b> <code>Family ID</code> and <code>First Name</code>. Everything else is optional.</p>
  <ul class="list-disc pl-5">
   <li><b>Relationship:</b> <code>HOF</code> = head of the family. Others: WIFE, SON, DAUGHTER, FATHER, MOTHER … (unknown or "-" is saved as OTHER).</li>
   <li><b>Family ID</b> is kept as the family code. Importing again updates the same family; it never creates duplicates, and empty cells never erase existing data.</li>
   <li>"NA", "N/A", "-", "Not Available" are treated as empty. Dates like <code>04-Dec-1998</code> or <code>04/12/1998</code> are fine.</li>
   <li>Mobile is optional (many members do not have one). Pincode <code>0</code> is ignored.</li>
   <li>Save from Excel as <b>CSV UTF-8</b>.</li>
  </ul>
  @if($canExport)
  <h3 class="font-display text-lg mt-3">Export members</h3>
  <form method="get" action="{{ route('admin.members.export') }}" class="flex flex-wrap gap-2 items-end">
   <input class="input !w-40" name="district" placeholder="District (optional)">
   <input class="input !w-40" name="tehsil" placeholder="Tehsil (optional)">
   <select class="input !w-28" name="status"><option value="">Active</option><option value="inactive">Inactive</option></select>
   <button class="btn btn-ink" name="format" value="csv">Download CSV</button>
   <button class="btn btn-ghost" name="format" value="xlsx">Download Excel</button>
  </form>
  <p class="text-xs" style="color:var(--muted)">The export has the same columns as the import, so you can correct the file and import it back. You get only the members of your own area.</p>
  @endif
 </div>
</div>

<h3 class="font-display text-lg mt-6 mb-2">Recent imports</h3>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>#</th><th>Date</th><th>File</th><th>By</th><th>Families</th><th>Members</th><th>Skipped</th><th></th></tr></thead><tbody>
@forelse($batches as $b)<tr><td data-l="#">{{ $b->id }}</td><td data-l="Date">{{ $b->created_at?->format('d-M-Y H:i') }}</td><td data-l="File">{{ $b->file_name }}</td><td data-l="By">{{ $b->user?->name }}</td>
 <td data-l="Families">+{{ $b->families_created }} / ~{{ $b->families_updated }}</td><td data-l="Members">+{{ $b->members_created }} / ~{{ $b->members_updated }}</td><td data-l="Skipped">{{ $b->skipped }}</td>
 <td>@if($b->undone_at)<span class="badge badge-warn">undone</span>@elseif($canImport)<form method="post" action="{{ route('admin.members.import.undo',$b) }}" onsubmit="return confirm('Undo this import? The families and members it added will be deactivated/archived.')">@csrf<button class="btn btn-sm btn-ghost">Undo</button></form>@endif</td></tr>
@empty<tr><td colspan="8" class="text-center py-6" style="color:var(--muted)">No imports yet.</td></tr>@endforelse</tbody></table></div>
@endsection
