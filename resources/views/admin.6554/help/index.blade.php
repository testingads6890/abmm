@extends('layouts.admin')
@section('title','Help & Support Requests')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">Help requests sent by members (education, medical, scholarship). You see only the requests of your area.</p>
<div class="flex flex-wrap gap-2 mb-3">
@foreach(\App\Models\HelpRequest::STATUSES as $k=>$l)<a class="btn btn-sm {{ $status===$k ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.help.index',['status'=>$k,'type'=>$type]) }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
<form class="ml-auto flex gap-2" method="get"><input type="hidden" name="status" value="{{ $status }}">
 <select class="input" name="type" onchange="this.form.submit()"><option value="">All types</option>@foreach(\App\Models\HelpRequest::TYPES as $k=>$l)<option value="{{ $k }}" @selected($type===$k)>{{ $l }}</option>@endforeach</select>
 <input class="input" name="q" value="{{ request('q') }}" placeholder="Title search"><button class="btn btn-sm btn-ink">Go</button></form></div>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Member</th><th>District</th><th>Amount</th><th></th></tr></thead><tbody>
@forelse($rows as $h)<tr><td class="whitespace-nowrap">{{ $h->created_at->format('d-M-Y') }}</td><td>{{ \App\Models\HelpRequest::TYPES[$h->type] ?? $h->type }}</td><td>{{ $h->title }}</td>
 <td>{{ trim(($h->member?->name ?? '').' '.($h->member?->surname ?? '')) ?: '—' }}<div class="text-xs" style="color:var(--muted)">{{ $h->member?->mobile }}</div></td><td>{{ $h->district }}</td><td>{{ $h->amount ? '₹'.number_format($h->amount) : '—' }}</td>
 <td><a class="btn btn-sm btn-ink" href="{{ route('admin.help.show',$h->id) }}">Open</a></td></tr>
@empty<tr><td colspan="7" class="text-center" style="color:var(--muted)">Nothing found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $rows->links() }}</div>
@endsection
