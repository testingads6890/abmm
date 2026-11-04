@extends('layouts.admin')
@section('title','Online Forms')
@section('content')
<div class="flex gap-2 mb-3">@can('forms.create')<a class="btn btn-primary btn-sm" href="{{ route('admin.forms.create') }}">+ New form</a>@endcan<a class="btn btn-sm btn-ink" href="{{ route('admin.forms.submissions') }}">Submissions / approvals →</a></div>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Title</th><th>Slug</th><th>Fee</th><th>Approval</th><th>Scope</th><th>Submissions</th><th>Active</th><th></th></tr></thead><tbody>
@forelse($forms as $f)<tr><td>{{ $f->title }}</td><td>{{ $f->slug }}</td><td>{{ $f->fee>0 ? $f->currency.' '.number_format($f->fee,2) : 'Free' }}</td><td>{{ $f->needs_approval?'Yes':'Auto' }}</td><td>{{ $f->country ?: 'All' }}{{ $f->city ? ' / '.$f->city : '' }}</td><td>{{ $f->submissions_count }}</td><td>{{ $f->is_active?'Yes':'No' }}</td>
 <td class="whitespace-nowrap">@can('forms.edit')<a class="btn btn-sm btn-ink" href="{{ route('admin.forms.edit',$f) }}">Edit</a>@endcan
 @can('forms.delete')<form method="post" action="{{ route('admin.forms.destroy',$f) }}" class="inline" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>
@empty<tr><td colspan="8" class="text-center" style="color:var(--muted)">No forms yet.</td></tr>@endforelse</tbody></table></div>
@endsection
