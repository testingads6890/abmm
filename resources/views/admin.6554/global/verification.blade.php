@extends('layouts.admin')
@section('title','Member verification')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="get" class="flex flex-wrap gap-2 mb-3"><select class="input !w-40" name="status">@foreach(['submitted','unverified','verified','rejected'] as $s)<option @selected((request('status')?:'submitted')===$s)>{{ $s }}</option>@endforeach</select>
 <input class="input !w-56" name="q" value="{{ request('q') }}" placeholder="Name / mobile / family code"><button class="btn btn-sm btn-ink">Filter</button></form>
@forelse($rows as $f)<div class="card p-4 mb-3 text-sm">
 <div class="flex flex-wrap justify-between gap-2"><div><b>{{ $f->head_name }}</b> · {{ $f->country_code }} {{ $f->mobile }} · {{ $f->city }}, {{ $f->country }}{{ $f->zone ? ' · '.$f->zone : '' }}</div>
  <div><span class="badge">{{ $f->verification_status }}</span> {{ $f->membership_no }}</div></div>
 <div class="mt-2">ID proof: {{ $f->id_proof_type ?: '—' }} @if($f->id_proof_path)<a class="btn btn-sm btn-ghost" target="_blank" href="{{ route('admin.verification.proof',$f) }}">View document</a>@endif</div>
 <div class="mt-1">References: @forelse($refs[$f->id] ?? [] as $x)<span class="badge {{ $x->status==='confirmed'?'badge-ok':'' }}">{{ $x->referee_name }} ({{ $x->referee_mobile }}) – {{ $x->status }}</span> @empty — @endforelse</div>
 @can('verification.verify')@if(in_array($f->verification_status,['submitted','rejected','unverified']))
 <form method="post" action="{{ route('admin.verification.decide',$f) }}" class="grid sm:grid-cols-5 gap-2 mt-3">@csrf
  <select class="input" name="member_category">@foreach(['general'=>'General','annual'=>'Annual','life'=>'Life','patron'=>'Patron'] as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
  <input class="input" name="chapter" value="{{ $f->chapter ?: $f->city }}" placeholder="Chapter"><input class="input" name="note" placeholder="Note">
  <label class="flex items-center gap-1"><input type="checkbox" name="override_refs" value="1"> References override</label>
  <div class="flex gap-2"><button name="decision" value="verified" class="btn btn-sm btn-primary">Verify</button><button name="decision" value="rejected" class="btn btn-sm btn-ghost">Reject</button></div></form>@endif @endcan</div>
@empty<div class="card p-5 text-center" style="color:var(--muted)">No records.</div>@endforelse{{ $rows->links() }}
@endsection
