@extends('layouts.admin')
@section('title','Business Reviews')
@section('content')
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Business</th><th>Reviewer</th><th>Rating</th><th>Comment</th><th>Status</th></tr></thead><tbody>
@foreach($rows as $r)<tr><td data-l="Business">{{ $r->business?->name }}</td><td data-l="Reviewer">{{ $r->reviewer_name }}</td><td data-l="Rating">{{ str_repeat('★',$r->rating) }}</td><td data-l="Comment">{{ $r->comment }}</td>
<td data-l="Status"><form method="post" action="{{ route('admin.business.reviews.status',$r) }}" class="flex gap-1">@csrf @method('PATCH')<select name="status" class="input !min-h-[34px] !py-1 text-xs">@foreach(['pending','approved','rejected'] as $s)<option @selected($r->status===$s)>{{ $s }}</option>@endforeach</select><button class="btn btn-ghost btn-sm">OK</button></form></td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $rows->links() }}</div>
@endsection
