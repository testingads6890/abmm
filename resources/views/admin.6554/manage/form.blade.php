@extends('layouts.admin')
@section('title',($id ? 'Edit: ' : 'New: ').$res['title'])
@section('content')
<form method="post" action="{{ $id ? route('admin.manage.update',[$key,$id]) : route('admin.manage.store',$key) }}" class="card p-4 grid gap-3 max-w-3xl">
 @csrf @if($id) @method('PUT') @endif
 @if($errors->any())<div class="text-sm" style="color:#b00">{{ $errors->first() }}</div>@endif
 @foreach($fields as $name=>$f)@php $v=old($name,$vals[$name] ?? ($f['type']==='checkbox' ? ($id ? 0 : 1) : '')); @endphp
  <label class="text-sm">{{ $f['label'] }}
  @switch($f['type'])
   @case('textarea')<textarea class="input" name="{{ $name }}" rows="4">{{ $v }}</textarea>@break
   @case('select')<select class="input" name="{{ $name }}">@if($f['nullable'])<option value="">—</option>@endif @foreach($f['options'] ?? [] as $ov=>$ol)<option value="{{ $ov }}" @selected((string)$v===(string)$ov)>{{ $ol }}</option>@endforeach</select>@break
   @case('checkbox')<input type="checkbox" name="{{ $name }}" value="1" @checked($v)>@break
   @case('number')<input class="input" type="number" step="any" name="{{ $name }}" value="{{ $v }}">@break
   @case('date')<input class="input" type="date" name="{{ $name }}" value="{{ $v ? substr((string)$v,0,10) : '' }}">@break
   @case('datetime')<input class="input" type="datetime-local" name="{{ $name }}" value="{{ $v ? str_replace(' ','T',substr((string)$v,0,16)) : '' }}">@break
   @default<input class="input" name="{{ $name }}" value="{{ $v }}">
  @endswitch</label>
 @endforeach
 <div><button class="btn btn-ink">Save</button> <a class="btn" href="{{ route('admin.manage',$key) }}">Cancel</a></div>
</form>
@endsection
