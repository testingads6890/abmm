@extends('layouts.admin')
@section('title', $meta['label'] . ' dashboard')
@section('content')
@php $tiles = [['Partners', $kpi['partners']], ['Active', $kpi['active']], ['Pending approval', $kpi['pending']], ['Subscribed now', $kpi['subscribed']], ['Expiring in 15 days', $kpi['expiring']],
  [ucfirst($meta['noun']) . 's listed', $kpi['records']], ['Leads', $kpi['leads']], ['Subscription revenue', number_format($kpi['revenue'], 2)], ['This month', number_format($kpi['revenue_month'], 2)]]; @endphp
<div class="flex flex-wrap gap-2 mb-4">
 <a class="btn btn-sm btn-ink" href="{{ route('admin.manage', $manage) }}">All {{ strtolower($meta['label']) }}s / approve / block →</a>
 <a class="btn btn-sm btn-ink" href="{{ route('admin.manage', 'partnerenq') }}">Leads →</a>
 @can('plans.view')<a class="btn btn-sm btn-ghost" href="{{ route('admin.plans') }}">Subscription plans →</a>@endcan
 @foreach(['bureau'=>'Marriage Bureau','organiser'=>'Event Organiser','recruiter'=>'Job Recruiter'] as $k=>$l)@if($k!==$kind)<a class="btn btn-sm btn-ghost" href="{{ route('admin.partners', $k) }}">{{ $l }}</a>@endif @endforeach
</div>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
 @foreach($tiles as [$l, $v])<div class="card p-4"><div class="text-xs opacity-70">{{ $l }}</div><div class="font-display text-2xl">{{ $v }}</div></div>@endforeach
</div>
<div class="grid lg:grid-cols-3 gap-4 mb-5">
 <div class="card p-4 lg:col-span-2"><h3 class="font-display text-lg mb-2">Partners (latest 50)</h3><div class="overflow-x-auto"><table class="tbl"><thead><tr><th>Name</th><th>State</th><th>City</th><th>Status</th><th>Subscription till</th><th>{{ ucfirst($meta['noun']) }}s</th><th>Leads</th><th></th></tr></thead><tbody>
  @forelse($partners as $p)<tr><td>{{ $p->name }}@if($p->verified) <span title="Verified">✔</span>@endif</td><td>{{ $p->pradesh }}</td><td>{{ $p->city }}</td><td>{{ $p->status }}</td>
   <td>{{ $p->sub_until ?: '— (locked)' }}</td><td>{{ $p->records }}</td><td>{{ $p->leads }}</td>
   <td class="whitespace-nowrap">@if($p->status==='pending')@can('partners.verify')<form class="inline" method="post" action="{{ route('admin.manage.act', [$manage, $p->id, 'approve']) }}">@csrf<button class="btn btn-sm btn-ink">Approve</button></form>@endcan @endif
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.manage.edit', [$manage, $p->id]) }}">Edit</a></td></tr>
  @empty<tr><td colspan="8" class="opacity-70">No partners yet.</td></tr>@endforelse</tbody></table></div></div>
 <div class="grid gap-4 content-start">
  <div class="card p-4"><h3 class="font-display text-lg mb-2">State-wise partners</h3>
   @forelse($byState as $s)<div class="flex justify-between text-sm py-1 border-b border-black/5"><span>{{ $s->state }}</span><b>{{ $s->n }}</b></div>@empty<div class="text-sm opacity-70">—</div>@endforelse</div>
  <div class="card p-4"><h3 class="font-display text-lg mb-2">Plans (what they pay)</h3>
   @forelse($plans as $p)<div class="text-sm py-1 border-b border-black/5"><b>{{ $p->name }}</b> — {{ $p->currency }} {{ number_format($p->price, 2) }} / {{ $p->period }}@if($p->max_items) · limit {{ $p->max_items }}@endif <span class="opacity-70">· {{ $p->subs }} active</span></div>
   @empty<div class="text-sm opacity-70">No plan yet. Create a plan for audience "{{ $meta['label'] }}" from the Plans screen.</div>@endforelse</div>
 </div>
</div>
@endsection
