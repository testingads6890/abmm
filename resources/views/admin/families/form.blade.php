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
  <div><label class="label">Country *</label><select class="input" name="country" id="f_country" required><option value="">Select</option>@foreach(collect($lk['country'] ?? [])->unique()->sort()->values() as $cn)<option @selected(strcasecmp((string)$f('country'),(string)$cn)===0)>{{ $cn }}</option>@endforeach</select></div>
  <small id="geoHint" style="color:var(--bad);grid-column:1/-1"></small>
  {!! $sel('surname','Surname',$lk['surname'] ?? [],true) !!}
  {{-- Caste hidden on this form (defaults to Maheshwari). To show it again, delete this comment wrapper and the hidden input below.
  {!! $sel('caste','Caste',array_keys($casteGotras) ?: ['Maheshwari']) !!}
  --}}
  <input type="hidden" name="caste" value="{{ $family->caste ?: 'Maheshwari' }}">
  {!! $sel('gotra','Gotra',$lk['gotra'] ?? []) !!}
  @php $in('head_name','Name (Head of the family)',true); $in('father_husband_name',"Father's / Husband name",true); $in('mother_name',"Mother's name"); $in('mobile','Mobile',true); $in('whatsapp','WhatsApp number (10 digits)'); @endphp
  {!! $sel('gender','Gender',$lk['gender'] ?? ['MALE','FEMALE'],true) !!}
  @php $in('member_count','No. of family members (incl. HOF)',true,'number'); $in('dob','Date of birth (dd/mmm/yyyy)',true,'date'); @endphp
  {!! $sel('marital_status','Marital status',$lk['marital_status'] ?? [],true) !!}
  <div id="anniv_wrap" style="display:none">@php $in('anniversary','Marriage anniversary',false,'date'); @endphp</div>
  {!! $sel('blood_group','Blood group',$lk['blood_group'] ?? []) !!}
  @php $in('email','Email',false,'email'); $in('phone','Telephone (STD + number)'); $in('aadhar','Aadhar number (12 digits)'.($family->aadhar_masked ? ' — '.$family->aadhar_masked : '')); @endphp
 </div></div>
 <div class="card p-5"><h3 class="font-display text-lg mb-4">Address</h3><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  {!! $sel('resident_type','Resident type',$lk['resident_type'] ?? [],true) !!}
  <div class="lg:col-span-2"><label class="label">Address *</label><input class="input" name="address" required value="{{ $f('address') }}"></div>
  <div><label class="label">State</label><select class="input" name="state" id="f_state" required><option value="">Select</option>@foreach(collect($tehsilMeta)->pluck('state')->filter()->unique()->sort() as $sn)<option @selected($f('state')===$sn)>{{ $sn }}</option>@endforeach</select></div>
  <div><label class="label">District *</label><select class="input" name="district" id="f_district" required><option value="">Select</option>@foreach(collect($tehsilMeta)->pluck('district')->filter()->unique()->sort() as $dn)<option @selected($f('district')===$dn)>{{ $dn }}</option>@endforeach</select></div>
  <div><label class="label">Tehsil / Zone *</label><select class="input" name="tehsil" id="f_tehsil" required><option value="">Select</option>@foreach($tehsilMeta as $tn=>$tm)<option @selected($f('tehsil')===$tn)>{{ $tn }}</option>@endforeach</select></div>
  <div><label class="label">Village / Town / City</label><input class="input" name="city" list="cityList" value="{{ $f('city') }}" autocomplete="off"><datalist id="cityList"></datalist></div>
  <div><label class="label">ABMM Pradesh</label><select class="input" name="pradesh" id="f_pradesh" required><option value="">Select</option>@foreach(collect($tehsilMeta)->pluck('pradesh')->filter()->unique()->sort() as $pn)<option @selected($f('pradesh')===$pn)>{{ $pn }}</option>@endforeach</select></div>
  <div><label class="label">ABMM Anchal <small style="color:var(--muted)">(filled from Pradesh)</small></label><select class="input" id="f_anchal"><option value="">Select</option>@foreach(array_keys(\App\Models\Lookup::anchalPradesh()) as $an)<option @selected(\App\Models\Lookup::anchalOf($family->pradesh)===$an)>{{ $an }}</option>@endforeach</select></div>
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
 var M=@json($tehsilMeta), CG=@json($casteGotras), G=@json($lk['gotra'] ?? []), CT=@json($cityByTehsil), CITIES=@json($lk['city'] ?? []);
 var VURL=@json(route('admin.geo.villages'));
function cityList(){ var dl=document.getElementById('cityList'), tv=document.getElementById('f_tehsil').value; if(!dl) return; var m=M[tv]; var saved=(tv && CT[tv]) ? CT[tv] : []; dl.innerHTML=''; function put(l){ var seen={}; dl.innerHTML=''; l.concat(saved).forEach(function(c){ if(c && !seen[c]){ seen[c]=1; var o=document.createElement('option'); o.value=c; dl.appendChild(o);} }); } put([]); if(!m) return; var q='?state='+encodeURIComponent(m.state||'')+'&district='+encodeURIComponent(m.district||'')+'&tehsil='+encodeURIComponent(tv); fetch(VURL+q,{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():{villages:[]}}).then(function(j){ put(j.villages||[]); }).catch(function(){ put([]); }); }
 document.getElementById('f_tehsil').addEventListener('change', cityList); cityList();
 var t=document.getElementById('f_tehsil'), d=document.getElementById('f_district'), st=document.getElementById('f_state'), co=document.getElementById('f_country');
 var allT=Object.keys(M), allD=uniq(allT.map(function(k){return M[k].district})), allS=uniq(allT.map(function(k){return M[k].state}));
 function uniq(a){return a.filter(function(v,i){return v&&a.indexOf(v)===i}).sort()}
 function set(id,v){var el=document.getElementById(id); if(el&&v){el.value=v;}}
 function fill(sel,vals,cur){var h='<option value="">Select</option>'; vals.forEach(function(v){h+='<option'+(v===cur?' selected':'')+'>'+v.replace(/&/g,'&amp;').replace(/</g,'&lt;')+'</option>'}); sel.innerHTML=h; if(cur&&vals.indexOf(cur)<0){sel.value='';}}
 function states(){return uniq(allT.filter(function(k){return !co.value||M[k].country===co.value}).map(function(k){return M[k].state}))}
 function districts(){return uniq(allT.filter(function(k){return !st.value||M[k].state===st.value}).map(function(k){return M[k].district}))}
 function tehsils(){return allT.filter(function(k){return (!st.value||M[k].state===st.value)&&(!d.value||M[k].district===d.value)}).sort()}
 co.addEventListener('change',function(){fill(st,states(),st.value); fill(d,districts(),d.value); fill(t,tehsils(),t.value);});
 st.addEventListener('change',function(){fill(d,districts(),d.value); fill(t,tehsils(),t.value);});
 d.addEventListener('change',function(){var k=allT.filter(function(x){return M[x].district===d.value})[0]; if(k){ if(!st.value) set('f_state',M[k].state); setPradesh(M[k].pradesh);} fill(t,tehsils(),t.value);});
 t.addEventListener('change',function(){var m=M[t.value]; if(!m) return; set('f_state',m.state); fill(d,districts(),m.district); set('f_district',m.district); setPradesh(m.pradesh); if(m.pincode) set('f_pincode',m.pincode);});
 fill(st,states(),st.value||@json($family->state)); fill(d,districts(),d.value||@json($family->district)); fill(t,tehsils(),t.value||@json($family->tehsil));
 var A=@json(\App\Models\Lookup::anchalPradesh()), an=document.getElementById('f_anchal'), pr=document.getElementById('f_pradesh'), P2A={};
 Object.keys(A).forEach(function(a){A[a].forEach(function(p){P2A[p]=a})});
 var allPr=uniq(Object.keys(A).reduce(function(acc,a){return acc.concat(A[a])},[]).concat(allT.map(function(k){return M[k].pradesh})));
 // Picking a Pradesh (from the tehsil or by hand) fills its Anchal. Picking an Anchal narrows the Pradesh list.
 function setPradesh(p){ fill(pr, allPr, p); if(!p) pr.value=''; if(an) an.value=P2A[p]||''; }
 an.addEventListener('change',function(){ var list=an.value&&A[an.value] ? A[an.value].slice().sort() : allPr; fill(pr, list, pr.value); });
 pr.addEventListener('change',function(){ if(P2A[pr.value] && an) an.value=P2A[pr.value]; });
// Mandatory order: Country > State > District > Tehsil > ABMM Pradesh > ABMM Anchal. A field opens only after the one before it is chosen.
function gate(){
  var steps=[[st,!!co.value],[d,!!st.value],[t,!!d.value],[pr,!!t.value],[an,!!pr.value]];
  steps.forEach(function(x){ if(x[0]) x[0].disabled=!x[1]; });
  var hint=document.getElementById('geoHint');
  if(hint) hint.textContent = !co.value ? 'Select Country first' : (!st.value ? 'Now select State' : (!d.value ? 'Now select District' : (!t.value ? 'Now select Tehsil' : (!pr.value ? 'Now select ABMM Pradesh' : ''))));
}
[co,st,d,t,pr,an].forEach(function(el){ if(el) el.addEventListener('change',gate); });
gate();
 // Marriage anniversary is shown only when marital status is Married
 var ms=document.querySelector('[name=marital_status]'), aw=document.getElementById('anniv_wrap');
 function syncAnniv(){ if(aw) aw.style.display = (ms && /^married$/i.test(ms.value.trim())) ? '' : 'none'; }
 if(ms){ ms.addEventListener('change', syncAnniv); syncAnniv(); }
 // Caste -> Gotra (only that caste's gotras)
 var c=document.querySelector('[name=caste]'), g=document.querySelector('[name=gotra]');
 function gotras(){var l=c&&c.value&&CG[c.value]?CG[c.value]:G; fill(g,l,g.value||@json($family->gotra));}
 if(c&&g){c.addEventListener('change',function(){g.value='';gotras();}); gotras();}
})();
</script>
@endsection
