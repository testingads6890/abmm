@extends('layouts.admin')
@section('title','Business — '.$business->name)
@section('content')
<form method="post" action="{{ route('admin.business.update',$business) }}" class="card p-5 grid sm:grid-cols-2 gap-4">@csrf @method('PUT')
 @foreach(['name'=>'Business name','type'=>'Business type','contact_person'=>'Contact person','mobile'=>'Mobile','email'=>'Email'] as $k=>$l)<div><label class="label">{{ $l }}</label><input class="input" name="{{ $k }}" value="{{ old($k,$business->$k) }}"></div>@endforeach
 <div><label class="label">Status</label><select class="input" name="status">@foreach(['pending','active','blocked'] as $x)<option @selected($business->status===$x)>{{ $x }}</option>@endforeach</select></div>
 <div class="sm:col-span-2"><label class="label">Address</label><input class="input" name="address" value="{{ old('address',$business->address) }}"></div>
 <div class="sm:col-span-2"><label class="label">About</label><textarea class="input" rows="3" name="description">{{ old('description',$business->description) }}</textarea></div>
 <div class="sm:col-span-2 flex gap-3"><button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="{{ route('admin.business.index') }}">Back</a><a class="btn btn-ghost" target="_blank" href="{{ route('admin.print',['business',$business->id]) }}?print=1">🖨 Print</a></div></form>
@endsection
