@extends('layouts.admin')
@section('title','Toppers Wall '.$year)
@section('content')
<style>@media print{aside,header,footer,.no-print{display:none!important}.card{box-shadow:none!important;border:1px solid #ddd}body{background:#fff!important}}</style>
<div class="flex items-center gap-2 mb-3 no-print"><a class="btn btn-sm btn-ghost" href="?year={{ $year-1 }}">‹</a><b class="font-display text-lg">{{ $year }}</b><a class="btn btn-sm btn-ghost" href="?year={{ $year+1 }}">›</a>
 <button class="btn btn-sm btn-ink ml-auto" onclick="window.print()">Print / Save as PDF</button></div>
<h2 class="font-display text-2xl mb-1">Akhil Bharatvarshiya Maheshwari Mahasabha — Toppers {{ $year }}</h2>
<p class="text-sm mb-4 no-print" style="color:var(--muted)">Only approved ranks and exam selections. Choose "Save as PDF" in "Print" to share.</p>
@forelse($groups as $cat=>$items)
<h3 class="font-display text-xl mt-5 mb-2">{{ \App\Models\FeedPost::CATEGORIES[$cat] ?? $cat }}</h3>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
@foreach($items as $p)<div class="card p-3 flex gap-3 items-start">
 @if($p->image)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($p->image) }}" class="w-16 h-16 rounded object-cover" alt="">@endif
 <div><b>{{ trim(($p->member?->name ?? '').' '.($p->member?->surname ?? '')) ?: '—' }}</b>{{ $p->proof_verified ? ' ✔' : '' }}<div>{{ $p->title }}</div><div class="text-xs" style="color:var(--muted)">{{ $p->district ?: $p->state }}</div></div></div>@endforeach
</div>
@empty<div class="card p-6 text-center" style="color:var(--muted)">{{ $year }} has no approved topper yet.</div>@endforelse
@endsection
