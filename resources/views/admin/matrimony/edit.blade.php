@extends('layouts.admin')
@section('title','Mangal Parinay — '.$p->parinay_id)
@section('content')
<form method="post" action="{{ route('admin.matrimony.update',$p) }}" class="card p-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">@csrf @method('PUT')
 <div class="col-span-full text-sm" style="color:var(--muted)">Parinay ID {{ $p->parinay_id }} · Family ID {{ $p->family?->family_code }} · Member ID {{ $p->member_code }} · Reg. {{ $p->registered_at?->format('d-M-Y') }} · <span class="badge">{{ $p->status }}</span></div>
 @foreach(['name'=>'Name','surname'=>'Surname','qualification'=>'Qualification','degree'=>'Degree','occupation_type'=>'Occupation type','occupation'=>'Occupation','height'=>'Height','mobile'=>'Mobile','state'=>'State','district'=>'District','pincode'=>'Pincode','payment_ref'=>'Payment ID'] as $k=>$l)
  <div><label class="label">{{ $l }}</label><input class="input" name="{{ $k }}" value="{{ old($k,$p->$k) }}"></div>@endforeach
 <div><label class="label">Gender</label><select class="input" name="gender"><option @selected($p->gender==='Male')>Male</option><option @selected($p->gender==='Female')>Female</option></select></div>
 <div><label class="label">Date of birth</label><input class="input" type="date" name="dob" value="{{ old('dob',$p->dob->format('Y-m-d')) }}"></div>
 <label class="flex items-center gap-2 text-sm pt-6"><input type="hidden" name="manglik" value="0"><input type="checkbox" name="manglik" value="1" @checked($p->manglik) class="accent-orange-600"> Manglik</label>
 <div><label class="label">Marital status</label><select class="input" name="marital_status"><option value="">Select</option>@foreach($lk['parinay_marital'] ?? [] as $o)<option @selected(old('marital_status',$p->marital_status)===$o)>{{ $o }}</option>@endforeach</select></div>
 <div><label class="label">Gotra</label><select class="input" name="gotra"><option value="">Select</option>@foreach($lk['gotra'] ?? [] as $o)<option @selected(old('gotra',$p->gotra)===$o)>{{ $o }}</option>@endforeach</select></div>
 <div><label class="label">Diet</label><select class="input" name="diet"><option value="">Select</option>@foreach($lk['diet'] ?? [] as $o)<option @selected(old('diet',$p->diet)===$o)>{{ $o }}</option>@endforeach</select></div>
 <div><label class="label">Height (cm)</label><input class="input" type="number" min="100" max="230" name="height_cm" value="{{ old('height_cm',$p->height_cm) }}"></div>
 @php $curBand = collect($incomeBands)->first(fn($b)=>$p->annual_income!==null && $p->annual_income>=$b['min'] && ($b['max']===null || $p->annual_income<=$b['max']))['label'] ?? ''; @endphp
 <div><label class="label">Income (yearly)</label><select class="input" name="income_band"><option value="">Select</option>@foreach($incomeBands as $b)<option @selected(old('income_band',$curBand)===$b['label'])>{{ $b['label'] }}</option>@endforeach</select></div>
 <div><label class="label">City</label><input class="input" name="city" value="{{ old('city',$p->city) }}"></div>
 <div><label class="label">Country</label><select class="input" name="country"><option value="">Select</option>@foreach($lk['country'] ?? [] as $x)<option @selected(old('country',$p->country)===$x)>{{ $x }}</option>@endforeach</select></div>
 <label class="flex items-center gap-2 text-sm pt-6"><input type="hidden" name="visible" value="0"><input type="checkbox" name="visible" value="1" @checked($p->visible) class="accent-orange-600"> Visible in search</label>
 <div class="sm:col-span-2"><label class="label">Address</label><input class="input" name="address" value="{{ old('address',$p->address) }}"></div>
@if($p->partner_pref)@php $pp=(array)$p->partner_pref; @endphp<div class="col-span-full p-3 rounded-xl text-sm" style="background:#fff7e6"><b>Partner preferences (member ne bhari):</b>
  Age {{ $pp['age_min'] ?? '—' }}–{{ $pp['age_max'] ?? '—' }} · Height {{ $pp['height_min'] ?? '—' }}–{{ $pp['height_max'] ?? '—' }} cm · Income min {{ isset($pp['income_min']) ? '₹'.number_format($pp['income_min']) : '—' }} · Manglik: {{ $pp['manglik'] ?? 'any' }}
  <br>Qualification: {{ implode(', ',$pp['qualification'] ?? []) ?: '—' }} · Marital: {{ implode(', ',$pp['marital'] ?? []) ?: '—' }} · State: {{ implode(', ',$pp['state'] ?? []) ?: '—' }} · Diet: {{ implode(', ',$pp['diet'] ?? []) ?: '—' }}
  @if(! empty($pp['note']))<br>Note: {{ $pp['note'] }}@endif</div>@endif
 <div class="col-span-full"><label class="label">About</label><textarea class="input" rows="3" name="about">{{ old('about',$p->about) }}</textarea></div>
 @include('admin.custom._inputs',['form'=>'matrimony','values'=>$p->extra ?? []])
 <div class="col-span-full flex gap-3"><button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="{{ route('admin.matrimony.index') }}">Back</a><a class="btn btn-ghost" target="_blank" href="{{ route('admin.print',['parinay',$p->id]) }}?print=1">🖨 Print</a></div></form>
@endsection
