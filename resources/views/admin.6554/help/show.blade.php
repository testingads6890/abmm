@extends('layouts.admin')
@section('title','Help: '.$h->title)
@section('content')
<a class="text-sm underline" href="{{ route('admin.help.index',['status'=>$h->status]) }}">‹ Back to list</a>
<div class="grid lg:grid-cols-3 gap-4 mt-3">
 <div class="card p-5 lg:col-span-2 grid gap-2">
  <div class="text-sm" style="color:var(--muted)">{{ \App\Models\HelpRequest::TYPES[$h->type] ?? $h->type }} · <b>{{ \App\Models\HelpRequest::STATUSES[$h->status] ?? $h->status }}</b></div>
  <h2 class="font-display text-2xl">{{ $h->title }}</h2>
  <p class="whitespace-pre-line">{{ $h->details }}</p>
  <div class="text-sm grid sm:grid-cols-2 gap-1 mt-2">
   <div>Member: <b>{{ trim(($h->member?->name ?? '').' '.($h->member?->surname ?? '')) ?: '—' }}</b> {{ $h->member?->mobile }}</div><div>Amount: {{ $h->amount ? '₹'.number_format($h->amount) : '—' }}</div>
   <div>Pradesh: {{ $h->pradesh ?: '—' }}</div><div>District: {{ $h->district ?: '—' }}</div><div>Bheji: {{ $h->created_at->format('d-M-Y H:i') }}</div>
   @if($h->decided_at)<div>Last update: {{ $h->decided_at->format('d-M-Y H:i') }}</div>@endif
  </div>
  @if($h->committee_note)<div class="text-sm p-2 rounded" style="background:#fff7e6">Committee note: {{ $h->committee_note }}</div>@endif
  <div class="mt-2 flex flex-wrap gap-2">@forelse($h->documents ?? [] as $i=>$p)<a class="btn btn-sm btn-ink" target="_blank" href="{{ route('admin.help.doc',[$h->id,$i]) }}">Document {{ $i+1 }}</a>@empty<span class="text-sm" style="color:var(--muted)">No documents.</span>@endforelse</div>
 </div>
 <div class="grid gap-4 content-start">
  @can('help.edit')
  <form method="post" action="{{ route('admin.help.decide',$h->id) }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Status update</h3>
   <select class="input" name="status">@foreach(\App\Models\HelpRequest::STATUSES as $k=>$l)<option value="{{ $k }}" @selected(old('status',$h->status)===$k)>{{ $l }}</option>@endforeach</select>
   <textarea class="input" rows="3" name="note" placeholder="Note shown to the member (required for reject / forward)">{{ old('note',$h->committee_note) }}</textarea>
   <button class="btn btn-primary">Save & notify the member</button></form>
  @endcan
  @can('help.delete')<form method="post" action="{{ route('admin.help.destroy',$h->id) }}" onsubmit="return confirm('Remove permanently?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Delete</button></form>@endcan
 </div>
</div>
@endsection
