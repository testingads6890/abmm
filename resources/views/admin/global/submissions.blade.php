@extends('layouts.admin')
@section('title','Form submissions')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="get" class="flex flex-wrap gap-2 mb-3"><select class="input !w-60" name="form_id"><option value="">All forms</option>@foreach($forms as $f)<option value="{{ $f->id }}" @selected(request('form_id')==$f->id)>{{ $f->title }}</option>@endforeach</select>
 <select class="input !w-36" name="status"><option value="">Status</option>@foreach(['pending','approved','rejected'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select><button class="btn btn-sm btn-ink">Filter</button></form>
@forelse($rows as $s)<div class="card p-4 mb-3 text-sm">
 <div class="flex flex-wrap justify-between gap-2"><div><b>{{ $s->form->title }}</b> · {{ $s->reference }} · {{ $s->city }} {{ $s->zone }}</div>
  <div><span class="badge">{{ $s->status }}</span> <span class="badge {{ $s->payment_status==='paid'?'badge-ok':'' }}">fee: {{ $s->payment_status }}</span></div></div>
 <div class="grid sm:grid-cols-2 gap-x-6 mt-2">@foreach($s->form->fields as $fl)<div><span style="color:var(--muted)">{{ $fl['label'] }}:</span> {{ $fl['type']==='file' ? ($s->data[$fl['key']] ?? null ? '[file uploaded]' : '—') : (is_bool($s->data[$fl['key']] ?? null) ? (($s->data[$fl['key']]) ? 'Yes':'No') : ($s->data[$fl['key']] ?? '—')) }}</div>@endforeach</div>
 @if($s->status==='pending')@can('forms.approve')<form method="post" action="{{ route('admin.forms.decide',$s) }}" class="flex flex-wrap gap-2 mt-3">@csrf<input class="input !w-72" name="remark" placeholder="Remark (optional)">
  <button name="decision" value="approved" class="btn btn-sm btn-primary">Approve</button><button name="decision" value="rejected" class="btn btn-sm btn-ghost">Reject</button></form>@endcan
 @elseif($s->remark)<div class="mt-2" style="color:var(--muted)">Remark: {{ $s->remark }}</div>@endif</div>
@empty<div class="card p-5 text-center" style="color:var(--muted)">No submissions.</div>@endforelse{{ $rows->links() }}
@endsection
