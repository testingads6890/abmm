@extends('layouts.admin')
@section('title','Review: '.$p->title)
@section('content')
<a class="text-sm underline" href="{{ route('admin.feed.index',['status'=>$p->status]) }}">‹ Back to list</a>
<div class="grid lg:grid-cols-3 gap-4 mt-3">
 <div class="card p-5 lg:col-span-2 grid gap-2">
  <div class="text-sm" style="color:var(--muted)">{{ \App\Models\FeedPost::TYPES[$p->type] ?? $p->type }} · {{ \App\Models\FeedPost::CATEGORIES[$p->category] ?? '—' }} · <b>{{ \App\Models\FeedPost::STATUSES[$p->status] ?? $p->status }}</b></div>
  <h2 class="font-display text-2xl">{{ $p->title }}</h2>
  @if($p->body)<p class="whitespace-pre-line">{{ $p->body }}</p>@endif
  @if($p->image)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($p->image) }}" alt="" class="rounded max-h-96 object-contain">@endif
  <div class="text-sm grid sm:grid-cols-2 gap-1 mt-2">
   <div>Member: <b>{{ trim(($p->member?->name ?? '').' '.($p->member?->surname ?? '')) ?: '—' }}</b> {{ $p->member?->mobile }}</div>
   <div>Bheja: {{ $p->submitter?->name ?? '—' }}</div>
   <div>Pradesh: {{ $p->pradesh ?: '—' }}</div><div>District: {{ $p->district ?: '—' }}</div>
   <div>Submit: {{ $p->created_at?->format('d-M-Y H:i') }}</div><div>Likes: {{ $p->likes_count }}</div>
  </div>
  @if($p->review_note)<div class="text-sm p-2 rounded" style="background:#fff7e6">Note: {{ $p->review_note }}</div>@endif
  @if($p->proof)<div class="mt-2"><a class="btn btn-sm btn-ink" target="_blank" href="{{ route('admin.feed.proof',$p->id) }}">View proof (private)</a> {{ $p->proof_verified ? '✔ Verified' : '' }}</div>@else<div class="text-sm" style="color:var(--muted)">Proof was not uploaded.</div>@endif
 </div>
 <div class="grid gap-4 content-start">
  @can('feed.approve')
  @if($p->status !== 'approved')
  <form method="post" action="{{ route('admin.feed.approve',$p->id) }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Approve</h3>
   <label class="label">Who should the push go to?</label>
   <select class="input" name="audience"><option value="district">That district</option><option value="state">That state</option><option value="all">Everyone</option><option value="none">No push</option></select>
   @if($p->proof)<label class="flex gap-2 text-sm"><input type="checkbox" name="verified" value="1"> Proof checked (Verified badge)</label>@endif
   <button class="btn btn-primary">Approve & Publish</button></form>
  @endif
  @if($p->status !== 'rejected')
  <form method="post" action="{{ route('admin.feed.decide',[$p->id,'changes']) }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Needs changes</h3>
   <textarea class="input" rows="2" name="note" placeholder="What should the member correct?" required></textarea><button class="btn btn-ink">Send back</button></form>
  <form method="post" action="{{ route('admin.feed.decide',[$p->id,'reject']) }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Reject</h3>
   <textarea class="input" rows="2" name="note" placeholder="Reason" required></textarea><button class="btn btn-ghost">Reject</button></form>
  @endif
  @endcan
  @can('feed.edit')
  <div class="card p-4 flex flex-wrap gap-2">
   <form method="post" action="{{ route('admin.feed.flag',[$p->id,'pinned']) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $p->pinned ? 'Unpin' : '📌 Pin' }}</button></form>
   @if($p->proof)<form method="post" action="{{ route('admin.feed.flag',[$p->id,'proof_verified']) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $p->proof_verified ? 'Remove verified' : '✔ Verified mark' }}</button></form>@endif
  </div>
  @endcan
  @can('feed.delete')<form method="post" action="{{ route('admin.feed.destroy',$p->id) }}" onsubmit="return confirm('Remove permanently?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Delete</button></form>@endcan
 </div>
</div>
@endsection
