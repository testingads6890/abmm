@extends('layouts.admin')
@section('title','Member · Data Bank')
@section('content')
<div class="flex flex-wrap justify-between items-center gap-3 mb-3"><nav class="text-sm" style="color:var(--muted)">Dashboard / Data Bank / <b>Member</b></nav><span class="flex gap-2"><a class="btn btn-ghost btn-sm" href="{{ route('admin.members.import') }}">Import / Export CSV</a><a class="btn btn-primary btn-sm" href="{{ route('admin.families.create') }}">+ Add New Family</a></span></div>
<form class="card p-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
  <input class="input lg:col-span-2" name="q" value="{{ request('q') }}" placeholder="Surname, then first name or mobile">
  @php $ms = function($k,$l,$opts){ $sel=(array)request($k); $h='<details class="ms relative"><summary class="input cursor-pointer flex justify-between items-center">'.e($l).($sel?' <span class="badge">'.count($sel).'</span>':'').'</summary><div class="ms-box card p-2 absolute z-30 mt-1 w-full max-h-64 overflow-auto"><input class="input mb-2" placeholder="Search" oninput="var q=this.value.toLowerCase();this.parentNode.querySelectorAll(\'label\').forEach(function(x){x.style.display=x.textContent.toLowerCase().indexOf(q)>-1?\'\':\'none\'})">'; foreach($opts as $o){ $h.='<label class="flex gap-2 items-center py-1 text-sm"><input type="checkbox" name="'.$k.'[]" value="'.e($o).'" '.(in_array($o,$sel,true)?'checked':'').'> '.e($o).'</label>'; } return $h.'</div></details>'; }; @endphp
  @foreach(['tehsil'=>'Tehsil','blood_group'=>'Blood Group','business_type'=>'Business Type','education'=>'Education','gender'=>'Gender','occupation'=>'Occupation','profession'=>'Profession','surname'=>'Surname','gotra'=>'Gotra','pradesh_avail'=>'ABMM Pradesh Availability'] as $k=>$l)
    {!! $ms($k,$l,$k==='pradesh_avail' ? ['yes','no'] : ($lk[$k] ?? [])) !!}@endforeach
  <select class="input" name="status"><option value="">Active</option><option @selected(request('status')==='inactive')>inactive</option><option @selected(request('status')==='moved')>moved</option></select>
  <div class="flex gap-2"><button class="btn btn-ink flex-1">Search</button><a class="btn btn-ghost" href="{{ route('admin.families.index') }}">Reset</a></div>
</form>
@if(! request()->hasAny(['q','surname','tehsil','blood_group','business_type','education','occupation','profession','gender','gotra','pradesh_avail','status']))<p class="text-sm mb-3" style="color:var(--muted)">Please use above filters then press Search button to load records. (Maximum 250 records are shown; for more use Reports → Family / Member and download Excel.)</p>@endif
<div class="flex justify-between text-sm mb-2"><span style="color:var(--muted)">{{ $families->total() }} families</span>
 <form>@foreach(request()->except('per','page') as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach Show <select name="per" class="input !inline-block !w-auto !min-h-[34px] !py-1" onchange="this.form.submit()">@foreach([10,25,50,100,250] as $n)<option @selected(request('per',25)==$n)>{{ $n }}</option>@endforeach</select> entries</form></div>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Family ID</th><th>Family Head</th><th>Father's/ Husband Name</th><th>Contact</th><th>Address</th><th>No. of family member (Including HOF)</th><th>Action</th></tr></thead><tbody>
@forelse($families as $f)<tr><td data-l="Family ID">{{ $f->family_code }}</td><td data-l="Head"><b>{{ $f->surname }} {{ $f->head_name }}</b> @if($f->status!=='active')<span class="badge badge-warn">{{ $f->status }}</span>@endif @if($f->source==='app')<span class="badge" title="Registered via mobile app">App</span>@endif</td><td data-l="Father/Husband">{{ $f->father_husband_name }}</td><td data-l="Contact">{{ $f->mobile }}</td>
<td data-l="Address" class="text-xs leading-5">{{ $f->address }}<br>ABMM Pradesh : {{ $f->pradesh }}<br>District : {{ $f->district }}<br>Tehsil : {{ $f->tehsil }}<br>Pincode : {{ $f->pincode }}<br>Reg. Date: {{ $f->created_at->format('d-M-Y') }}<br>Last Updated: {{ $f->updated_at->gt($f->created_at->copy()->addMinute()) ? $f->updated_at->format('d-M-Y') : 'N/A' }}<br>Member ID: {{ $f->family_code }}</td><td data-l="Members">{{ $f->member_count }}</td>
<td><div class="acts">
 <a class="act act-ok" title="View" href="{{ route('admin.families.show',$f) }}">🔍</a>
 <a class="act act-ink" title="Edit" href="{{ route('admin.families.edit',$f) }}">✎</a>
 <a class="act act-ok" title="Add New Member" href="{{ route('admin.families.show',$f) }}#add-member">+</a>
 <a class="act act-info" title="Print" target="_blank" href="{{ route('admin.print',['head',$f->id]) }}?print=1">🖨</a>
 <a class="act act-warn" title="Family Tree" target="_blank" href="{{ route('admin.print',['family',$f->id]) }}">🌳</a>
 <form method="post" action="{{ route('admin.families.toggle',$f) }}" @if($f->status!=='inactive') onsubmit="return confirm('Deactivate the whole family?')" @endif>@csrf<button class="act {{ $f->status==='inactive' ? 'act-ok' : 'act-bad' }}" title="{{ $f->status==='inactive' ? 'Activate' : 'De-Activate' }}">{{ $f->status==='inactive' ? '✓' : '✕' }}</button></form></div></td></tr>
@empty<tr><td colspan="7" class="text-center py-8" style="color:var(--muted)">Nothing found.</td></tr>@endforelse</tbody></table></div>
<div class="mt-4">{{ $families->links() }}</div>
@endsection
