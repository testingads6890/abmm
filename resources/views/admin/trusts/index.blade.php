@extends('layouts.admin')
@section('title','Trusts')
@section('content')
@php
$ms=function($k,$l,$opts){ $sel=(array)request($k); $h='<details class="ms relative"><summary class="input cursor-pointer flex justify-between items-center">'.e($l).($sel?' <span class="badge">'.count($sel).'</span>':'').'</summary><div class="ms-box card p-2 absolute z-30 mt-1 w-full max-h-64 overflow-auto"><input class="input mb-2" placeholder="Search" oninput="var q=this.value.toLowerCase();this.parentNode.querySelectorAll(\'label\').forEach(function(x){x.style.display=x.textContent.toLowerCase().indexOf(q)>-1?\'\':\'none\'})">'; foreach($opts as $o){ $h.='<label class="flex gap-2 items-center py-1 text-sm"><input type="checkbox" name="'.$k.'[]" value="'.e($o).'" '.(in_array($o,$sel,true)?'checked':'').'> '.e($o).'</label>'; } return $h.'</div></details>'; };
$allDist=collect($sd)->flatten()->unique()->sort()->values()->all();
$allTrusts=collect($ct)->flatten()->unique()->sort()->values()->all();
$xh=array_keys(\App\Support\CustomForm::display('trust',[],true));
$countries=$lk['country'] ?? ['INDIA'];
@endphp
@if($errors->any())<div class="card p-3 mb-3 text-sm" style="border-color:#c0392b">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@if(empty($ct))<div class="card p-3 mb-3 text-sm">First <a class="underline" href="{{ route('admin.masters.index','trust_category') }}">Master Data → Trust Category</a> and <a class="underline" href="{{ route('admin.masters.index','trust_name') }}">Name of Trust</a> add values in.</div>@endif
<form class="card p-4 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-3">
 <input class="input lg:col-span-2" name="q" value="{{ request('q') }}" placeholder="Name / email / mobile / city">
 {!! $ms('trust_category','Category',array_keys($ct)) !!}{!! $ms('trust_name','Name of Trust',$allTrusts) !!}
 {!! $ms('gender','Gender',$lk['gender'] ?? []) !!}{!! $ms('marital_status','Marital Status',$lk['marital_status'] ?? []) !!}
 {!! $ms('state','Govt. State',array_keys($sd)) !!}{!! $ms('district','District',$allDist) !!}
 <select class="input" name="per">@foreach([10,25,50,100] as $n)<option value="{{ $n }}" @selected((int)request('per',25)===$n)>Show {{ $n }}</option>@endforeach</select>
 <button class="btn btn-ink">Filter</button><a class="btn" href="{{ url()->current() }}">Reset</a>
 @can('trusts.create')<button type="button" class="btn btn-primary" onclick="openTrust()">+ Add</button>@endcan
 @can('trusts.export')<span class="flex gap-2 text-sm items-center col-span-full">Download list:
  @foreach(['pdf'=>'PDF','xlsx'=>'Excel','csv'=>'CSV'] as $f=>$l)<a class="btn btn-ghost btn-sm" href="{{ request()->fullUrlWithQuery(['export'=>$f]) }}">{{ $l }}</a>@endforeach</span>@endcan
</form>
<div class="mb-2 text-sm"><b>Total Records:</b> {{ $rows->total() }}</div>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>#</th><th>Category / Name of Trust</th><th>Name</th><th>Email ID</th><th>Mobile Number</th><th>DOB</th><th>Gender / Marital Status</th><th>City</th><th>Aadhar Card</th><th>Govt. State / District</th>@foreach($xh as $xl)<th>{{ $xl }}</th>@endforeach<th>Submitted</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $i=>$t)<tr>
 <td data-l="#">{{ $rows->firstItem()+$i }}</td><td data-l="Category"><b>{{ $t->trust_name }}</b><div class="text-xs" style="color:var(--muted)">{{ $t->trust_category }}</div></td>
 <td data-l="Name"><b>{{ $t->name }}</b></td><td data-l="Email ID">{{ $t->email }}</td><td data-l="Mobile Number">{{ $t->contact }}</td><td data-l="DOB">{{ $t->dob?->format('d-M-Y') }}</td>
 <td data-l="Gender">{{ $t->gender }}<div class="text-xs" style="color:var(--muted)">{{ $t->marital_status }}</div></td><td data-l="City">{{ $t->city }}</td><td data-l="Aadhar Card" class="text-xs">{{ $t->aadharMasked() }}</td>
 <td data-l="Govt. State" class="text-xs">{{ $t->state }}<br>{{ $t->district }}</td>
 @foreach(\App\Support\CustomForm::display('trust',$t->extra,true) as $xl=>$xv)<td data-l="{{ $xl }}">{{ $xv }}</td>@endforeach
 <td data-l="Submitted">@if($t->submitted_count)<span class="badge badge-ok">{{ $t->submitted_count }}×</span>@else<span class="badge">No</span>@endif</td>
 <td><div class="acts">
  @can('trusts.export')<a class="act act-info" title="Download PDF" href="{{ route('admin.trusts.pdf',$t) }}">⬇</a>@endcan
  @can('trusts.edit')<button type="button" class="act act-ok" title="Edit" onclick="openTrust(@js($t))">✎</button>@endcan
  @can('trusts.submit')<button type="button" class="act act-warn" title="Submit to department" onclick="openSubmit({{ $t->id }},@js($t->name))">➤</button>@endcan
  @can('trusts.delete')<form method="post" action="{{ route('admin.trusts.destroy',$t) }}" onsubmit="return confirm('Hataayein?')">@csrf @method('DELETE')<button class="act act-bad" title="Delete">🗑</button></form>@endcan
 </div></td></tr>@empty<tr><td colspan="30" class="py-6" style="color:var(--muted)">No records found.</td></tr>@endforelse</tbody></table></div>
<div class="my-4">{{ $rows->links() }}</div>

<dialog id="trustDlg" class="card p-0 w-full max-w-3xl"><form method="post" id="trustForm" action="{{ route('admin.trusts.store') }}" class="p-5 grid sm:grid-cols-2 gap-3">@csrf<span id="trustMethod"></span>
 <h3 class="font-display text-lg col-span-full" id="trustTitle">Add</h3>
 <div><label class="label">Category *</label><select class="input" name="trust_category" id="tCat" required onchange="fillTrusts();showCat()"><option value="">-- Select --</option>@foreach(array_keys($ct) as $c)<option>{{ $c }}</option>@endforeach</select></div>
 <div><label class="label">Name of Trust *</label><select class="input" name="trust_name" id="tTrust" required><option value="">-- Select Category first --</option></select></div>
 <div><label class="label">Name *</label><input class="input" name="name" required maxlength="200"></div>
 <div><label class="label">Email ID *</label><input class="input" type="email" name="email" required></div>
 <div><label class="label">Date of Birth</label><input class="input" type="date" name="dob" max="{{ now()->toDateString() }}"></div>
 <div><label class="label">Mobile Number *</label><input class="input" name="contact" required maxlength="10" inputmode="numeric"></div>
 <div><label class="label">Gender</label><select class="input" name="gender"><option value="">-- Select --</option>@foreach($lk['gender'] ?? [] as $g)<option>{{ $g }}</option>@endforeach</select></div>
 <div><label class="label">Marital Status</label><select class="input" name="marital_status"><option value="">-- Select --</option>@foreach($lk['marital_status'] ?? [] as $g)<option>{{ $g }}</option>@endforeach</select></div>
 <div><label class="label">City</label><input class="input" name="city" maxlength="100"></div>
 <div><label class="label">Aadhar Card No.</label><input class="input" name="aadhar" maxlength="12" inputmode="numeric" placeholder="12 digits (blank when editing = keep old)"></div>
 <details class="col-span-full"><summary class="cursor-pointer text-sm font-semibold">Address (optional)</summary><div class="grid sm:grid-cols-2 gap-3 mt-3">
  <div class="sm:col-span-2"><label class="label">Address</label><textarea class="input" rows="2" name="address" maxlength="600"></textarea></div>
  <div><label class="label">Country</label><select class="input" name="country">@foreach($countries as $c)<option @selected($c==='INDIA')>{{ $c }}</option>@endforeach</select></div>
  <div><label class="label">Govt. State</label><select class="input" name="state" id="tState" onchange="fillDist()"><option value="">-- Select --</option>@foreach(array_keys($sd) as $s)<option>{{ $s }}</option>@endforeach</select></div>
  <div><label class="label">District</label><input class="input" name="district" id="tDist" list="distList" maxlength="80"><datalist id="distList"></datalist></div>
  <div><label class="label">Pincode</label><input class="input" name="pincode" inputmode="numeric" maxlength="6"></div></div></details>
 <div class="col-span-full text-sm font-semibold" id="xHead" style="display:none">Extra details of the category</div>
 @include('admin.custom._inputs',['form'=>'trust','values'=>[]])
 <div class="col-span-full flex justify-end gap-2 form-actions"><button type="button" class="btn" onclick="trustDlg.close()">Close</button><button class="btn btn-primary">Save</button></div>
</form></dialog>

<dialog id="subDlg" class="card p-0 w-full max-w-md"><form method="post" id="subForm" class="p-5 grid gap-3">@csrf
 <h3 class="font-display text-lg">Submit to Department</h3><p class="text-sm" id="subName"></p>
 <div><label class="label">Department *</label><select class="input" name="department_id" required id="deptSel"><option value="">-- Select --</option>@foreach($departments as $d)<option value="{{ $d->id }}" data-fee="{{ $d->fee }}" data-fields="{{ $d->getRawOriginal('fields') ?: '[]' }}">{{ $d->name }} @if($d->fee>0)(₹{{ number_format($d->fee) }})@else(Free)@endif</option>@endforeach</select></div>
 <div id="deptFields" class="grid gap-2"></div>
 <p class="text-xs" style="color:var(--muted)" id="feeNote">Choosing a department with a fee opens Razorpay payment; once the payment is verified the Trust details + PDF are emailed to the department.@unless($razorpay) <b>(Razorpay keys are not set yet.)</b>@endunless</p>
 @if($departments->isEmpty())<p class="text-sm">First <a class="underline" href="{{ route('admin.departments') }}">Departments</a> add.</p>@endif
 <div class="flex justify-end gap-2 form-actions"><button type="button" class="btn" onclick="subDlg.close()">Close</button><button class="btn btn-primary">Submit / Pay</button></div>
</form></dialog>
<script>
document.getElementById('deptSel')?.addEventListener('change',function(){const box=document.getElementById('deptFields');box.innerHTML='';let f=[];try{f=JSON.parse(this.selectedOptions[0]?.dataset.fields||'[]')}catch(e){}
 f.forEach(x=>{const w=document.createElement('div');const l=document.createElement('label');l.className='label';l.textContent=x.label+(x.required?' *':'');w.appendChild(l);let i;
  if(x.type==='select'){i=document.createElement('select');i.innerHTML='<option value="">--</option>'+x.options.map(o=>'<option>'+o+'</option>').join('')}else if(x.type==='textarea'){i=document.createElement('textarea')}else{i=document.createElement('input');i.type=x.type==='number'?'number':(x.type==='date'?'date':'text')}
  i.name='df['+x.key+']';i.className='input';if(x.required)i.required=true;w.appendChild(i);box.appendChild(w)})});
const SD=@json($sd), CT=@json($ct);
function fillDist(){const s=document.getElementById('tState').value;document.getElementById('distList').innerHTML=(SD[s]||[]).map(d=>'<option value="'+d.replace(/"/g,'&quot;')+'">').join('')}
function fillTrusts(sel){const c=document.getElementById('tCat').value,el=document.getElementById('tTrust');el.innerHTML='<option value="">-- Select --</option>'+(CT[c]||[]).map(n=>'<option>'+n.replace(/</g,'&lt;')+'</option>').join('');if(sel)el.value=sel}
function showCat(){const c=document.getElementById('tCat').value;let any=false;document.querySelectorAll('#trustForm [data-x]').forEach(e=>{const w=e.closest('[data-cat]');const on=!w||w.dataset.cat===c;if(w){w.style.display=on?'':'none';e.disabled=!on}});
 document.querySelectorAll('#trustForm [data-cat]').forEach(w=>{if(w.style.display!=='none')any=true});document.getElementById('xHead').style.display=any?'':'none'}
function openTrust(t){const f=document.getElementById('trustForm');f.reset();const edit=t&&t.id;
 document.getElementById('trustTitle').textContent=edit?'Edit':'Add';
 f.action=edit?'{{ url('admin/trusts') }}/'+t.id:'{{ route('admin.trusts.store') }}';
 document.getElementById('trustMethod').innerHTML=edit?'<input type="hidden" name="_method" value="PUT">':'';
 fillTrusts();
 if(edit){['trust_category','name','email','contact','gender','marital_status','city','address','country','state','pincode'].forEach(k=>{if(f[k])f[k].value=t[k]||''});f.dob.value=(t.dob||'').substring(0,10);fillTrusts(t.trust_name);fillDist();f.district.value=t.district||'';
  f.querySelectorAll('[data-x]').forEach(e=>{const x=(t.extra||{})[e.dataset.x];if(e.type==='checkbox')e.checked=!!x;else e.value=x??''})}
 showCat();trustDlg.showModal()}
function openSubmit(id,name){document.getElementById('subForm').action='{{ url('admin/trusts') }}/'+id+'/submit';document.getElementById('subName').textContent=name;subDlg.showModal()}
</script>
@endsection
