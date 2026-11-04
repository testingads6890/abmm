@extends('layouts.admin')
@section('title', $family->exists ? 'Edit Family '.$family->family_code : 'Add New Family')
@section('content')
@php
 $f = fn($k)=>old($k,$family->$k);
 $sel = function($name,$label,$opts,$req=false) use ($f) { echo '<div><label class="label">'.$label.($req?' *':'').'</label><select class="input" name="'.$name.'"'.($req?' required':'').'><option value="">Select</option>'; foreach($opts as $o) echo '<option'.(strcasecmp((string)$f($name),(string)$o)===0?' selected':'').'>'.e($o).'</option>'; echo '</select></div>'; };
 $in = function($name,$label,$req=false,$type='text') use ($f) { echo '<div><label class="label">'.$label.($req?' *':'').'</label><input class="input" type="'.$type.'" name="'.$name.'" value="'.e($type==='date' && $f($name) ? \Illuminate\Support\Carbon::parse($f($name))->format('Y-m-d') : $f($name)).'"'.($req?' required':'').'></div>'; };
@endphp
<form method="post" enctype="multipart/form-data" action="{{ $family->exists ? route('admin.families.update',$family) : route('admin.families.store') }}" class="grid gap-5">
 @csrf @if($family->exists) @method('PUT') @endif
 <div class="card p-5"><h3 class="font-display text-lg mb-4">Family head</h3><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  {!! $sel('surname','Surname',$lk['surname'] ?? [],true) !!}
  {!! $sel('caste','Caste',array_keys($casteGotras) ?: ['Maheshwari']) !!}
  {!! $sel('gotra','Gotra',$lk['gotra'] ?? []) !!}
  @php $in('head_name','Name (Head of the family)',true); $in('father_husband_name',"Father's / Husband name",true); $in('mother_name',"Mother's name"); $in('mobile','Mobile',true); @endphp
  {!! $sel('gender','Gender',$lk['gender'] ?? ['MALE','FEMALE'],true) !!}
  @php $in('member_count','No. of family members (incl. HOF)',true,'number'); $in('dob','Date of birth',false,'date'); @endphp
  {!! $sel('marital_status','Marital status',$lk['marital_status'] ?? []) !!}
  @php $in('anniversary','Marriage anniversary',false,'date'); @endphp
  {!! $sel('blood_group','Blood group',$lk['blood_group'] ?? []) !!}
  @php $in('email','Email',false,'email'); $in('phone','Telephone (STD + number)'); $in('aadhar','Aadhar number (12 digits)'.($family->aadhar_masked ? ' — '.$family->aadhar_masked : '')); @endphp
 </div></div>
 <div class="card p-5"><h3 class="font-display text-lg mb-4">Address</h3><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  {!! $sel('resident_type','Resident type',$lk['resident_type'] ?? [],true) !!}
  <div class="lg:col-span-2"><label class="label">Address *</label><input class="input" name="address" required value="{{ $f('address') }}"></div>
  {!! $sel('country','Country',$lk['country'] ?? [],true) !!}
  <div><label class="label">State</label><select class="input" name="state" id="f_state"><option value="">Select</option>@foreach(collect($tehsilMeta)->pluck('state')->filter()->unique()->sort() as $sn)<option @selected($f('state')===$sn)>{{ $sn }}</option>@endforeach</select></div>
  <div><label class="label">District *</label><select class="input" name="district" id="f_district" required><option value="">Select</option>@foreach(collect($tehsilMeta)->pluck('district')->filter()->unique()->sort() as $dn)<option @selected($f('district')===$dn)>{{ $dn }}</option>@endforeach</select></div>
  <div><label class="label">Tehsil / Town</label><select class="input" name="tehsil" id="f_tehsil"><option value="">Select</option>@foreach($tehsilMeta as $tn=>$tm)<option @selected($f('tehsil')===$tn)>{{ $tn }}</option>@endforeach</select></div>
  <div><label class="label">Village / Town / City *</label><input class="input" name="city" required list="cityList" value="{{ $f('city') }}"><datalist id="cityList">@foreach($lk['city'] ?? [] as $c)<option value="{{ $c }}">@endforeach</datalist></div>
  <div><label class="label">ABMM Pradesh</label><select class="input" name="pradesh" id="f_pradesh"><option value="">Select</option>@foreach(collect($tehsilMeta)->pluck('pradesh')->filter()->unique()->sort() as $pn)<option @selected($f('pradesh')===$pn)>{{ $pn }}</option>@endforeach</select></div>
  <div><label class="label">Pincode * <small style="color:var(--muted)">(automatic from tehsil)</small></label><input class="input" name="pincode" id="f_pincode" required maxlength="6" inputmode="numeric" value="{{ $f('pincode') }}"></div>
 </div></div>
 <div class="card p-5"><h3 class="font-display text-lg mb-4">Education, business and others</h3><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  {!! $sel('education','Education',$lk['education'] ?? []) !!}@php $in('education_detail','Education detail'); @endphp
  {!! $sel('occupation','Occupation',$lk['occupation'] ?? []) !!}@php $in('occupation_detail','Occupation detail'); @endphp
  {!! $sel('business_type','Business type',$lk['business_type'] ?? []) !!}{!! $sel('profession','Profession',$lk['profession'] ?? []) !!}
  {!! $sel('income_range','Income',$lk['income'] ?? [],true) !!}
  <div><label class="label">Photo</label><input class="input" type="file" name="photo" accept="image/*"></div>
  @if($family->exists)<div>{!! '' !!}<label class="label">Status</label><select class="input" name="status">@foreach(['active','inactive','moved'] as $s)<option @selected($f('status')===$s)>{{ $s }}</option>@endforeach</select></div>@endif
  <label class="flex items-center gap-2 text-sm"><input type="hidden" name="has_mediclaim" value="0"><input type="checkbox" name="has_mediclaim" value="1" @checked($f('has_mediclaim')) class="accent-orange-600"> All members have mediclaim</label>
  <label class="flex items-center gap-2 text-sm"><input type="hidden" name="pakshik_member" value="0"><input type="checkbox" name="pakshik_member" value="1" @checked($f('pakshik_member')) class="accent-orange-600"> Maheshwari Pakshik (Nagpur) member</label>
 </div></div>
 @if(count(\App\Support\CustomForm::fields('family')))<div class="card p-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4"><h3 class="font-display text-lg col-span-full">Additional details</h3>@include('admin.custom._inputs',['form'=>'family','values'=>$family->extra ?? []])</div>@endif
 <div class="flex gap-3"><button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="{{ route('admin.families.index') }}">Cancel</a></div>
</form>
<script>
(function(){
 var M=@json($tehsilMeta), CG=@json($casteGotras), G=@json($lk['gotra'] ?? []);
 var t=document.getElementById('f_tehsil'), d=document.getElementById('f_district'), st=document.getElementById('f_state');
 var allT=Object.keys(M), allD=uniq(allT.map(function(k){return M[k].district})), allS=uniq(allT.map(function(k){return M[k].state}));
 function uniq(a){return a.filter(function(v,i){return v&&a.indexOf(v)===i}).sort()}
 function set(id,v){var el=document.getElementById(id); if(el&&v){el.value=v;}}
 function fill(sel,vals,cur){var h='<option value="">Select</option>'; vals.forEach(function(v){h+='<option'+(v===cur?' selected':'')+'>'+v.replace(/&/g,'&amp;').replace(/</g,'&lt;')+'</option>'}); sel.innerHTML=h; if(cur&&vals.indexOf(cur)<0){sel.value='';}}
 function districts(){return uniq(allT.filter(function(k){return !st.value||M[k].state===st.value}).map(function(k){return M[k].district}))}
 function tehsils(){return allT.filter(function(k){return (!st.value||M[k].state===st.value)&&(!d.value||M[k].district===d.value)}).sort()}
 st.addEventListener('change',function(){fill(d,districts(),d.value); fill(t,tehsils(),t.value);});
 d.addEventListener('change',function(){var k=allT.filter(function(x){return M[x].district===d.value})[0]; if(k){ if(!st.value) set('f_state',M[k].state); set('f_pradesh',M[k].pradesh);} fill(t,tehsils(),t.value);});
 t.addEventListener('change',function(){var m=M[t.value]; if(!m) return; set('f_state',m.state); fill(d,districts(),m.district); set('f_district',m.district); set('f_pradesh',m.pradesh); if(m.pincode) set('f_pincode',m.pincode);});
 fill(d,districts(),d.value||@json($family->district)); fill(t,tehsils(),t.value||@json($family->tehsil));
 // Caste -> Gotra (only that caste's gotras)
 var c=document.querySelector('[name=caste]'), g=document.querySelector('[name=gotra]');
 function gotras(){var l=c&&c.value&&CG[c.value]?CG[c.value]:G; fill(g,l,g.value||@json($family->gotra));}
 if(c&&g){c.addEventListener('change',function(){g.value='';gotras();}); gotras();}
})();
</script>
@endsection
