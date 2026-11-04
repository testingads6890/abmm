@extends('layouts.admin')
@section('title','Profile')
@section('content')
<div class="grid lg:grid-cols-3 gap-4">
<div class="card p-5"><div class="w-20 h-20 rounded-full grid place-items-center text-white font-display text-3xl mb-3" style="background:linear-gradient(135deg,var(--ink),var(--saffron))">{{ mb_substr($u->name,0,1) }}</div>
 <h2 class="font-display text-xl">{{ $u->name }} <span class="badge">{{ $u->role }}</span></h2><p class="text-sm" style="color:var(--muted)">{{ $u->email }} · {{ $u->mobile }}</p>
 <dl class="mt-4 text-sm grid gap-1">@foreach(['Address'=>$u->address,'Country'=>$u->country,'ABMM Pradesh'=>$u->pradesh,'Govt. State'=>null,'District'=>$u->district,'Tehsil'=>$u->tehsil,'Pincode'=>$u->pincode] as $k=>$v)@if($v)<div class="flex justify-between border-b pb-1" style="border-color:var(--line)"><dt style="color:var(--muted)">{{ $k }}</dt><dd>{{ $v }}</dd></div>@endif @endforeach</dl></div>
<form method="post" action="{{ route('admin.profile.update') }}" class="card p-5 grid gap-3 content-start">@csrf @method('PUT')<h3 class="font-display text-lg">Profile edit</h3>
 <input class="input" name="name" value="{{ old('name',$u->name) }}" required><input class="input" name="mobile" value="{{ old('mobile',$u->mobile) }}" maxlength="10" placeholder="Mobile"><input class="input" name="address" value="{{ old('address',$u->address) }}" placeholder="Address">
 <div class="grid grid-cols-2 gap-2"><input class="input" name="country" value="{{ old('country',$u->country) }}"><input class="input" name="tehsil" value="{{ old('tehsil',$u->tehsil) }}" placeholder="Tehsil"></div><input class="input" name="pincode" value="{{ old('pincode',$u->pincode) }}" placeholder="Pincode"><button class="btn btn-primary">Save</button></form>
<div class="card p-5"><h3 class="font-display text-lg mb-3">Activity</h3>@foreach($logs as $l)<div class="flex justify-between text-sm py-2 border-b" style="border-color:var(--line)"><span>{{ $l->created_at?->format('d-M-Y') }}<br><small style="color:var(--muted)">{{ $l->created_at?->format('h:i A') }}</small></span><span class="badge {{ $l->action==='LOGIN'?'badge-ok':'' }}">{{ ucfirst(strtolower($l->action)) }}</span></div>@endforeach</div>
</div>
@endsection
