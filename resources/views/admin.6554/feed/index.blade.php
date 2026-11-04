@extends('layouts.admin')
@section('title','Achievements & News')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">Achievements sent by members are reviewed here. Once you approve, they appear in the community feed and the member gets a congratulation notification.</p>
<div class="flex flex-wrap gap-2 mb-3">
@can('feed.create')<a class="btn btn-sm btn-primary" href="{{ route('admin.feed.create',['type'=>'news']) }}">+ New News</a><a class="btn btn-sm btn-ink" href="{{ route('admin.feed.create',['type'=>'condolence']) }}">+ Condolence notice</a>@endcan
<a class="btn btn-sm btn-ink" href="{{ route('admin.feed.toppers') }}">Toppers Wall</a>
</div>
<div class="flex flex-wrap gap-2 mb-3">
@foreach(\App\Models\FeedPost::STATUSES as $k=>$l)<a class="btn btn-sm {{ $status===$k ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.feed.index',['status'=>$k,'type'=>$type]) }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
<form class="ml-auto flex gap-2" method="get"><input type="hidden" name="status" value="{{ $status }}">
 <select class="input" name="type" onchange="this.form.submit()"><option value="">All types</option>@foreach(\App\Models\FeedPost::TYPES as $k=>$l)<option value="{{ $k }}" @selected($type===$k)>{{ $l }}</option>@endforeach</select>
 <input class="input" name="q" value="{{ request('q') }}" placeholder="Title search"><button class="btn btn-sm btn-ink">Go</button></form></div>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Title</th><th>Member</th><th>District</th><th>Likes</th><th></th></tr></thead><tbody>
@forelse($rows as $p)<tr>
 <td class="whitespace-nowrap">{{ $p->created_at->format('d-M-Y') }}</td><td>{{ \App\Models\FeedPost::TYPES[$p->type] ?? $p->type }}</td><td>{{ \App\Models\FeedPost::CATEGORIES[$p->category] ?? '—' }}</td>
 <td>{{ $p->pinned ? '📌 ' : '' }}{{ $p->title }}{{ $p->proof_verified ? ' ✔' : '' }}</td><td>{{ trim(($p->member?->name ?? '').' '.($p->member?->surname ?? '')) ?: '—' }}</td><td>{{ $p->district }}</td><td>{{ $p->likes_count }}</td>
 <td><a class="btn btn-sm btn-ink" href="{{ $p->type==='achievement' ? route('admin.feed.show',$p->id) : route('admin.feed.edit',$p->id) }}">{{ $p->type==='achievement' ? 'Open' : 'Edit' }}</a></td></tr>
@empty<tr><td colspan="8" class="text-center" style="color:var(--muted)">Nothing found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $rows->links() }}</div>
@endsection
