@extends('layouts.admin')
@section('title','Upload Committee Member Data')
@section('content')
@php $age = fn($d)=>$d?->format('d-M-Y'); @endphp
<div class="flex flex-wrap justify-between items-center gap-3 mb-3"><nav class="text-sm" style="color:var(--muted)">Dashboard / Data Bank / <b>Upload Committee Member Data</b></nav>
 <div class="flex gap-2">
  @can('committee.import')<button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('cUp').classList.add('open')">Upload Committee Member Data</button>@endcan
  @can('committee.create')<button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('cAdd').classList.add('open')">+ Add single member</button>@endcan</div></div>
@if($errors->any())<div class="rounded-xl p-3 text-sm badge-bad mb-3">{{ $errors->first() }}</div>@endif

<form class="card p-4 grid sm:grid-cols-4 gap-3 mb-3 no-print"><input class="input sm:col-span-2" name="q" value="{{ request('q') }}" placeholder="Name or Mobile">
 <select class="input" name="designation"><option value="">Designation</option>@foreach($designations as $d)<option @selected(request('designation')===$d)>{{ $d }}</option>@endforeach</select>
 <button class="btn btn-ink">Search</button></form>

<div class="flex flex-wrap justify-between items-center gap-2 text-sm mb-2 no-print">
 <form>@foreach(request()->except('per','page') as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach Show <select name="per" class="input !inline-block !w-auto !min-h-[34px] !py-1" onchange="this.form.submit()">@foreach([10,25,50,100] as $n)<option @selected(request('per',10)==$n)>{{ $n }}</option>@endforeach</select> entries</form>
 <label>Search: <input class="input !inline-block !w-auto !min-h-[34px] !py-1" oninput="var q=this.value.toLowerCase();document.querySelectorAll('#ct tbody tr').forEach(function(r){r.style.display=r.textContent.toLowerCase().indexOf(q)>-1?'':'none'})"></label></div>
<p class="text-sm mb-2" style="color:var(--bad)">Total Records : {{ $rows->total() }}</p>
<div class="card p-2 overflow-x-auto"><table id="ct" class="tbl stack"><thead><tr><th>Name</th><th>Designation</th><th>Mobile</th><th>Date Of Birth</th><th>Address</th><th class="no-print">Action</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td data-l="Name"><b>{{ $r->name }}</b>@if($r->surname && ! str_contains(strtolower($r->name), strtolower($r->surname))) {{ $r->surname }}@endif</td><td data-l="Designation">{{ $r->designation }}</td><td data-l="Mobile">{{ $r->mobile }}</td><td data-l="Date Of Birth">{{ $age($r->dob) }}</td><td data-l="Address">{{ $r->address }}</td>
 <td class="no-print"><div class="acts">
  @can('committee.edit')<button type="button" class="act act-warn" title="Edit" onclick='editC(@json($r->only(["id","name","surname","designation","level","mobile","address","district","tehsil"]) + ["dob"=>$r->dob?->format("Y-m-d")]))'>✎</button>@endcan
  @can('committee.delete')<form method="post" action="{{ route('admin.committee.destroy',$r) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="act act-bad" title="Delete">🗑</button></form>@endcan</div></td></tr>
@empty<tr><td colspan="6" class="text-center py-8" style="color:var(--muted)">No data available</td></tr>@endforelse</tbody></table></div>
<div class="flex flex-wrap justify-between items-center gap-3 mt-3 no-print"><div class="text-sm" style="color:var(--muted)">Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} entries</div>{{ $rows->links() }}</div>
<div class="flex gap-2 justify-center mt-4 no-print"><button class="btn btn-ghost btn-sm" onclick="print()">🖨 Print</button><a class="btn btn-ghost btn-sm" href="{{ route('admin.reports','committee') }}?export=xlsx&designation={{ request('designation') }}&q={{ request('q') }}">Excel</a><a class="btn btn-ghost btn-sm" href="{{ route('admin.reports','committee') }}?export=pdf&designation={{ request('designation') }}&q={{ request('q') }}">PDF</a></div>

{{-- Upload modal (same as the original: file + sample + DOB warning) --}}
<div id="cUp" class="modal" onclick="if(event.target===this)this.classList.remove('open')"><form method="post" enctype="multipart/form-data" action="{{ route('admin.committee.import') }}" class="card grid gap-3">@csrf
 <h3 class="font-display text-xl">Upload Committee Member Data</h3>
 <label class="label">Browse File (.xls / .csv)</label><input class="input" type="file" name="file" accept=".csv,.txt,.xls,.xlsx" required>
 <a class="text-sm underline" href="{{ route('admin.committee.sample') }}">Download File</a>
 <p class="text-xs" style="color:var(--bad)">Date Of Birth Is Mandatory, Without Date Of Birth Data Will Be Discarded.</p>
 <p class="text-xs" style="color:var(--muted)">Columns: name, surname, designation, mobile, dob (YYYY-MM-DD), address</p>
 <div class="flex justify-end gap-2"><button type="button" class="btn btn-ghost" onclick="this.closest('.modal').classList.remove('open')">Close</button><button class="btn btn-primary">Save Changes</button></div></form></div>

{{-- Add single member --}}
<div id="cAdd" class="modal" onclick="if(event.target===this)this.classList.remove('open')"><form method="post" action="{{ route('admin.committee.store') }}" class="card grid gap-3">@csrf<h3 class="font-display text-xl">Add committee member</h3>
 <input class="input" name="name" placeholder="Name *" required>
 <select class="input" name="surname"><option value="">Surname</option>@foreach($surnames as $s)<option>{{ $s }}</option>@endforeach</select>
 <select class="input" name="designation" required><option value="">Designation *</option>@foreach($designations as $d)<option>{{ $d }}</option>@endforeach</select>
 <select class="input" name="level">@foreach(['country','pradesh','state','district','tehsil'] as $l)<option>{{ $l }}</option>@endforeach</select>
 <input class="input" name="mobile" placeholder="Mobile" maxlength="10" inputmode="numeric"><input class="input" type="date" name="dob" required title="Date of birth"><input class="input" name="address" placeholder="Address">
 <input type="hidden" name="is_active" value="1">
 @include('admin.custom._inputs',['form'=>'committee','values'=>[]])
 <div class="flex justify-end gap-2"><button type="button" class="btn btn-ghost" onclick="this.closest('.modal').classList.remove('open')">Close</button><button class="btn btn-primary">Save Changes</button></div></form></div>

{{-- Update modal --}}
<div id="cEdit" class="modal" onclick="if(event.target===this)this.classList.remove('open')"><form method="post" id="cf" class="card grid gap-3">@csrf @method('PUT')<h3 class="font-display text-xl">Update</h3>
 <label class="label">Name*</label><input class="input" name="name" id="c_name" required>
 <label class="label">Surname*</label><select class="input" name="surname" id="c_surname"><option value=""></option>@foreach($surnames as $s)<option>{{ $s }}</option>@endforeach</select>
 <input type="hidden" name="designation" id="c_designation"><input type="hidden" name="level" id="c_level"><input type="hidden" name="tehsil" id="c_tehsil"><input type="hidden" name="district" id="c_district"><input type="hidden" name="is_active" value="1">
 <label class="label">Mobile</label><input class="input" name="mobile" id="c_mobile" maxlength="10" inputmode="numeric">
 <label class="label">Date Of Birth</label><input class="input" type="date" name="dob" id="c_dob">
 <label class="label">Address</label><input class="input" name="address" id="c_address">
 @include('admin.custom._inputs',['form'=>'committee','values'=>[]])
 <div class="flex justify-end gap-2"><button type="button" class="btn btn-ghost" onclick="this.closest('.modal').classList.remove('open')">Close</button><button class="btn btn-primary">Update</button></div></form></div>
<script>function editC(r){const f=document.getElementById('cf');f.action=@json(url('admin/committee')).concat('/',r.id);for(const k of ['name','surname','designation','level','mobile','dob','address','tehsil','district'])document.getElementById('c_'+k).value=r[k]??'';document.querySelectorAll('#cEdit [data-x]').forEach(e=>{const x=(r.extra||{})[e.dataset.x];if(e.type==='checkbox')e.checked=!!x;else e.value=x??''});document.getElementById('cEdit').classList.add('open')}</script>
@endsection
