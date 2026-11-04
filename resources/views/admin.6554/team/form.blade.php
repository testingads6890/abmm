@extends('layouts.admin')
@section('title', $u ? 'Edit '.$u->name : 'New Team Member')
@section('content')
@php
  $editing = (bool) $u;
  $role = old('role', $u?->role ?? ($me->creatableRoles()[0] ?? 'district_operator'));
  $have = old('permissions', $u?->permissions ?? config('permissions.defaults.'.$role, []));
@endphp
<nav class="text-sm mb-3" style="color:var(--muted)">Dashboard / <a href="{{ route('admin.team.index') }}">Team</a> / <b>{{ $editing ? 'Edit' : 'New' }}</b></nav>
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="post" action="{{ $editing ? route('admin.team.update',$u) : route('admin.team.store') }}" class="grid lg:grid-cols-3 gap-4">
@csrf @if($editing) @method('PUT') @endif
<div class="card p-5 grid gap-3 content-start lg:col-span-1">
  <h3 class="font-display text-lg">Account</h3>
  <div><label class="label">Role</label>
    @if($editing)<input class="input" value="{{ $u->roleLabel() }}" disabled><input type="hidden" name="role" value="{{ $u->role }}">
    @else<select class="input" name="role" id="role">@foreach($me->creatableRoles() as $r)<option value="{{ $r }}" @selected($role===$r)>{{ $roles[$r]['label'] }}</option>@endforeach</select>@endif</div>
  <div><label class="label">Name</label><input class="input" name="name" value="{{ old('name',$u?->name) }}" required></div>
  <div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email',$u?->email) }}" required></div>
  <div><label class="label">Mobile (digits only, without country code)</label><input class="input" name="mobile" maxlength="15" value="{{ old('mobile',$u?->mobile) }}" required></div>
  <div><label class="label">Password {{ $editing ? '(blank = unchanged)' : '(min 10)' }}</label><input class="input" type="password" name="password" autocomplete="new-password" {{ $editing ? '' : 'required' }}></div>
  <h3 class="font-display text-lg mt-2">Area (only data of this area will be visible)</h3>
  <p class="text-xs" style="color:var(--muted)">Chain: Super Admin → State Sub-Admin → District Admin → City Admin → Zone Admin. Names <a class="underline" href="{{ route('admin.locations') }}">Locations</a> master.</p>
  @php $lock = fn($v) => (! $me->isSuper() && $v) ? 'readonly' : ''; @endphp
  <div id="fCountry"><label class="label">Country</label><input class="input" name="country" list="dl-country" value="{{ old('country', $u?->country ?? ($me->country ?: 'INDIA')) }}" {{ $lock($me->country) }}><datalist id="dl-country"></datalist></div>
  <div id="fState"><label class="label">State / Pradesh</label><input class="input" name="pradesh" list="dl-state" value="{{ old('pradesh', $u?->pradesh ?? $me->pradesh) }}" {{ $lock($me->pradesh) }} autocomplete="off"><datalist id="dl-state"></datalist></div>
  <div id="fDistrict"><label class="label">District</label><input class="input" name="district" list="dl-district" value="{{ old('district', $u?->district ?? $me->district) }}" {{ $lock($me->district) }} autocomplete="off"><datalist id="dl-district"></datalist></div>
  <div id="fCity"><label class="label">City</label><input class="input" name="city" list="dl-city" value="{{ old('city', $u?->city ?? $me->city) }}" {{ $lock($me->city) }} autocomplete="off"><datalist id="dl-city"></datalist></div>
  <div id="fZone"><label class="label">Zone (e.g. Zone 1)</label><input class="input" name="zone" list="dl-zone" value="{{ old('zone', $u?->zone) }}" autocomplete="off"><datalist id="dl-zone"></datalist></div>
</div>
<div class="card p-5 lg:col-span-2">
  <div class="flex justify-between items-center mb-3"><h3 class="font-display text-lg">Permissions</h3>
   <div class="flex gap-2 text-xs"><button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.perm:not(:disabled)').forEach(c=>c.checked=true)">Select all</button><button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.perm:not(:disabled)').forEach(c=>c.checked=false)">Clear all</button></div></div>
  <p class="text-xs mb-3" style="color:var(--muted)">You can only grant permissions that you have yourself. Greyed (disabled) options are not available to you.</p>
  <div class="grid sm:grid-cols-2 gap-3">
  @foreach($modules as $m=>$def)
   <fieldset class="border rounded-xl p-3" style="border-color:var(--line)"><legend class="px-2 text-sm font-semibold">{{ $def['label'] }}</legend>
    <div class="flex flex-wrap gap-x-4 gap-y-1">
    @foreach($def['actions'] as $a)@php($k="$m.$a")
     <label class="text-sm flex items-center gap-1 {{ in_array($k,$grantable) ? '' : 'opacity-50' }}"><input class="perm" type="checkbox" name="permissions[]" value="{{ $k }}" @checked(in_array($k,$have)) @disabled(! in_array($k,$grantable))> {{ ucfirst(str_replace('_',' ',$a)) }}</label>
    @endforeach</div></fieldset>
  @endforeach
  </div>
  <div class="mt-4 flex gap-2"><button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create' }}</button><a class="btn btn-ghost" href="{{ route('admin.team.index') }}">Cancel</a></div>
</div>
</form>
<script>
(function(){
 var role=document.getElementById('role') || {value:@json($u?->role)};
 var D=@json($defaults);
 var SHOW={country_admin:['Country'],state_admin:['State'],district_operator:['State','District'],city_admin:['State','District','City'],zone_coordinator:['State','District','City','Zone']};
 var F=document.querySelector('form');
 function inp(n){return F.querySelector('[name='+n+']');}
 function geo(parent,level,dl){
   var u='{{ url('/api/v1/geo') }}'+(parent?'?parent_id='+parent:''); if(level) u+=(parent?'&':'?')+'level='+level;
   return fetch(u,{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){var l=document.getElementById(dl);l.innerHTML='';(j.data||[]).forEach(function(x){var o=document.createElement('option');o.value=x.name;o.dataset.id=x.id;l.appendChild(o)});return j.data||[]}).catch(function(){return []});
 }
 function idOf(dl,val){var o=[].slice.call(document.getElementById(dl).options).filter(function(x){return x.value.toLowerCase()===String(val||'').toLowerCase()})[0];return o?o.dataset.id:null;}
 function chain(){
   geo(null,'country','dl-country').then(function(){var c=idOf('dl-country',inp('country').value); if(!c) return;
     geo(c,'state','dl-state').then(function(){var s=idOf('dl-state',inp('pradesh').value); if(!s) return;
       geo(s,'district','dl-district').then(function(){var d=idOf('dl-district',inp('district').value); if(!d) return;
         geo(d,'city','dl-city').then(function(){var t=idOf('dl-city',inp('city').value); if(!t) return;
           geo(t,'zone','dl-zone');});});});});
 }
 function sync(){var r=role.value,s=SHOW[r]||['State'];
   ['Country','State','District','City','Zone'].forEach(function(k){document.getElementById('f'+k).style.display=(k==='Country'?(r==='country_admin'||['city_admin','zone_coordinator'].indexOf(r)>-1):s.indexOf(k)>-1)?'block':'none';});
   (D[r]||[]).length && document.querySelectorAll('.perm:not(:disabled)').forEach(function(c){c.checked=D[r].indexOf(c.value)>-1;});}
 if(role.addEventListener) role.addEventListener('change',sync); sync(); chain();
 ['country','pradesh','district','city'].forEach(function(n){inp(n).addEventListener('change',chain);});
})();
</script>
@endsection
