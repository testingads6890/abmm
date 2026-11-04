@extends('layouts.admin')
@section('title','App Downloads')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">This list is shown on the Downloads screen of the member app. Each entry has a "Follow Link" button.</p>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Title</th><th>Link (URL)</th><th>Order</th><th>Show</th><th></th></tr></thead><tbody>
@foreach($rows as $d)<tr>
 <td><input class="input" form="d{{ $d->id }}" name="title" value="{{ $d->title }}" required></td><td><input class="input" form="d{{ $d->id }}" name="url" value="{{ $d->url }}" required></td>
 <td><input class="input !w-20" form="d{{ $d->id }}" type="number" name="sort" value="{{ $d->sort }}"></td><td><input type="checkbox" form="d{{ $d->id }}" name="is_active" value="1" @checked($d->is_active)></td>
 <td class="whitespace-nowrap"><form id="d{{ $d->id }}" method="post" action="{{ route('admin.downloads.update',$d->id) }}" class="inline">@csrf @method('PUT')<button class="btn btn-sm btn-ink">Save</button></form>
  @can('downloads.delete')<form method="post" action="{{ route('admin.downloads.destroy',$d->id) }}" class="inline" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>@endforeach
</tbody></table></div>
@can('downloads.create')<form method="post" action="{{ route('admin.downloads.store') }}" class="card p-5 grid sm:grid-cols-4 gap-3">@csrf<h3 class="font-display text-lg col-span-full">New link</h3>
 <input class="input sm:col-span-2" name="title" placeholder="Title" required><input class="input" name="url" placeholder="https://..." required><input class="input" type="number" name="sort" placeholder="Order"><button class="btn btn-primary col-span-full sm:col-span-1">Add</button></form>@endcan
@endsection
