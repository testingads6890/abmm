@extends('layouts.admin')
@section('title','Daily Message')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">The app Home screen shows one message every day. Today's message: {{ $today }}. State blank = all of India; a state-specific message is shown first to members of that state.</p>
<div class="flex items-center gap-2 mb-3"><a class="btn btn-sm btn-ghost" href="?month={{ $prev }}">‹</a><b class="font-display text-lg">{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</b><a class="btn btn-sm btn-ghost" href="?month={{ $next }}">›</a></div>

<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Date</th><th>State</th><th>Tithi</th><th>Message (Hindi)</th><th>English</th><th>Show</th><th></th></tr></thead><tbody>
@forelse($rows as $d)<tr>
 <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($d->show_on)->format('d M, D') }}</td><td>{{ $d->pradesh ?: 'All India' }}</td>
 <td><input class="input" form="m{{ $d->id }}" name="tithi" value="{{ $d->tithi }}"></td>
 <td><textarea class="input" rows="2" form="m{{ $d->id }}" name="body_hi" required>{{ $d->body_hi }}</textarea></td>
 <td><textarea class="input" rows="2" form="m{{ $d->id }}" name="body_en">{{ $d->body_en }}</textarea></td>
 <td><input type="checkbox" form="m{{ $d->id }}" name="is_active" value="1" @checked($d->is_active)></td>
 <td class="whitespace-nowrap">@can('engagement.edit')<form id="m{{ $d->id }}" method="post" action="{{ route('admin.daily.update',$d->id) }}" class="inline">@csrf @method('PUT')<button class="btn btn-sm btn-ink">Save</button></form>@endcan
  @can('engagement.delete')<form method="post" action="{{ route('admin.daily.destroy',$d->id) }}" class="inline" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>
@empty<tr><td colspan="7" class="text-center" style="color:var(--muted)">No message for this month.</td></tr>@endforelse
</tbody></table></div>

@can('engagement.create')
<div class="grid lg:grid-cols-2 gap-4">
<form method="post" action="{{ route('admin.daily.store') }}" enctype="multipart/form-data" class="card p-5 grid gap-3">@csrf<h3 class="font-display text-lg">One day message</h3>
 <input class="input" type="date" name="show_on" required>
 @if($pradeshList->count())<select class="input" name="pradesh"><option value="">All India</option>@foreach($pradeshList as $p)<option>{{ $p }}</option>@endforeach</select>@endif
 <input class="input" name="tithi" placeholder="Tithi / day (optional)">
 <textarea class="input" rows="3" name="body_hi" placeholder="Daily message (Hindi)" required></textarea>
 <textarea class="input" rows="2" name="body_en" placeholder="English (optional)"></textarea>
 <div><label class="label">Image (optional, max 2 MB)</label><input class="input" type="file" name="image" accept="image/*"></div>
 <button class="btn btn-primary">Save</button></form>
<form method="post" action="{{ route('admin.daily.bulk') }}" class="card p-5 grid gap-3">@csrf<h3 class="font-display text-lg">Whole month at once</h3>
 <p class="text-xs" style="color:var(--muted)">One day per line:<br><code>2026-11-01 | Daily message</code><br>ya <code>2026-11-01 | Kartik Shukla Pratipada | Daily message</code><br>The old message for that day is replaced.</p>
 @if($pradeshList->count())<select class="input" name="pradesh"><option value="">All India</option>@foreach($pradeshList as $p)<option>{{ $p }}</option>@endforeach</select>@endif
 <textarea class="input font-mono" rows="9" name="lines" required placeholder="2026-11-01 | ...&#10;2026-11-02 | ..."></textarea>
 <button class="btn btn-primary">Import</button></form>
</div>
@endcan
@endsection
