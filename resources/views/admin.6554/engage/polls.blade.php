@extends('layouts.admin')
@section('title','Polls & Surveys')
@section('content')
<div class="flex justify-between items-center mb-3"><p class="text-sm" style="color:var(--muted)">Poll = one question, Survey = many questions. Target: all / country / state / district / city / zone.</p>
 @can('polls.create')<a class="btn btn-sm btn-ink" href="{{ route('admin.polls.new') }}">+ New poll / survey</a>@endcan</div>
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Title</th><th>Type</th><th>Target</th><th>Window</th><th>Responses</th><th>Active</th><th></th></tr></thead><tbody>
@forelse($rows as $p)<tr><td>{{ $p->title }}</td><td>{{ ucfirst($p->kind) }}</td><td>{{ \App\Services\GeoTarget::label(json_decode($p->target ?: '[]', true) ?: []) }}</td>
 <td class="text-xs">{{ $p->starts_at ?: '—' }} → {{ $p->ends_at ?: '—' }}</td><td>{{ $counts[$p->id] ?? 0 }}</td><td>{{ $p->is_active ? 'Yes' : 'No' }}</td>
 <td class="whitespace-nowrap"><a class="btn btn-sm" href="{{ route('admin.polls.results',$p->id) }}">Results</a>
  @can('polls.edit')<a class="btn btn-sm" href="{{ route('admin.polls.edit',$p->id) }}">Edit</a>@endcan
  @can('polls.delete')<form class="inline" method="post" action="{{ route('admin.polls.destroy',$p->id) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm">Delete</button></form>@endcan</td></tr>
@empty<tr><td colspan="7" style="color:var(--muted)">No polls yet.</td></tr>@endforelse</tbody></table></div>
@endsection
