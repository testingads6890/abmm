@extends('layouts.admin')
@section('title','Festival Greetings')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">On the festival day the app shows a banner + greeting, and if "Send push" is ticked, a notification goes out at 7:30 am to everyone (or to that state).</p>
<div class="flex items-center gap-2 mb-3"><a class="btn btn-sm btn-ghost" href="?year={{ $year-1 }}">‹</a><b class="font-display text-lg">{{ $year }}</b><a class="btn btn-sm btn-ghost" href="?year={{ $year+1 }}">›</a></div>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Date</th><th>Festival</th><th>State</th><th>Greeting message</th><th>Push</th><th>Show</th><th></th></tr></thead><tbody>
@forelse($rows as $f)<tr>
 <td><input class="input" type="date" form="f{{ $f->id }}" name="festival_date" value="{{ $f->festival_date }}" required></td>
 <td><input class="input" form="f{{ $f->id }}" name="name" value="{{ $f->name }}" required></td><td>{{ $f->pradesh ?: 'All India' }}</td>
 <td><textarea class="input" rows="2" form="f{{ $f->id }}" name="message" required>{{ $f->message }}</textarea></td>
 <td><input type="checkbox" form="f{{ $f->id }}" name="notify" value="1" @checked($f->notify)>@if($f->notified_at)<div class="text-xs" style="color:var(--muted)">sent</div>@endif</td>
 <td><input type="checkbox" form="f{{ $f->id }}" name="is_active" value="1" @checked($f->is_active)></td>
 <td class="whitespace-nowrap">@can('engagement.edit')<form id="f{{ $f->id }}" method="post" action="{{ route('admin.festivals.update',$f->id) }}" class="inline">@csrf @method('PUT')<button class="btn btn-sm btn-ink">Save</button></form>@endcan
  @can('engagement.delete')<form method="post" action="{{ route('admin.festivals.destroy',$f->id) }}" class="inline" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>
@empty<tr><td colspan="7" class="text-center" style="color:var(--muted)">No festival this year.</td></tr>@endforelse
</tbody></table></div>
@can('engagement.create')
<form method="post" action="{{ route('admin.festivals.store') }}" enctype="multipart/form-data" class="card p-5 grid sm:grid-cols-2 gap-3">@csrf<h3 class="font-display text-lg col-span-full">New festival</h3>
 <input class="input" name="name" placeholder="Festival name (e.g. Mahesh Navami)" required><input class="input" type="date" name="festival_date" required>
 @if($pradeshList->count())<select class="input" name="pradesh"><option value="">All India</option>@foreach($pradeshList as $p)<option>{{ $p }}</option>@endforeach</select>@endif
 <div><input class="input" type="file" name="banner" accept="image/*"><span class="text-xs" style="color:var(--muted)">Banner (optional, max 2 MB)</span></div>
 <textarea class="input col-span-full" rows="3" name="message" placeholder="Greeting message" required></textarea>
 <label class="flex gap-2 text-sm"><input type="checkbox" name="notify" value="1" checked> Send push on that day</label><button class="btn btn-primary sm:col-span-1">Add</button></form>
@endcan
@endsection
