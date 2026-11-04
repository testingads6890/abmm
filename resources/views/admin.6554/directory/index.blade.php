@extends('layouts.admin')
@section('title','Samaj Directory')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">In the app Samaj Directory, each member sees only people from their own state. A phone number is shown only if the member allowed it. Below are the figures for your area.</p>
<div class="grid sm:grid-cols-3 gap-3 mb-4">
 <div class="card p-4"><div class="text-sm" style="color:var(--muted)">Active members in the directory</div><div class="text-3xl font-display">{{ number_format($stats['total'] - $stats['hidden']) }}</div></div>
 <div class="card p-4"><div class="text-sm" style="color:var(--muted)">Hid themselves</div><div class="text-3xl font-display">{{ number_format($stats['hidden']) }}</div></div>
 <div class="card p-4"><div class="text-sm" style="color:var(--muted)">Showing phone number</div><div class="text-3xl font-display">{{ number_format($stats['phone']) }}</div></div>
</div>
@can('directory.edit')
<form method="post" action="{{ route('admin.directory.settings') }}" class="card p-5 grid gap-3 mb-4">@csrf
 <h3 class="font-display text-lg">Settings (Super Admin)</h3>
 <label class="flex gap-2 text-sm"><input type="checkbox" name="on" value="1" @checked($on)> Directory on (turning it off switches it off in the whole app)</label>
 <div><label class="label">Keep the Directory OFF in these states (tick = off)</label>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-1 max-h-48 overflow-y-auto border rounded p-2">
   @foreach($pradeshList as $p)<label class="flex gap-2 text-sm"><input type="checkbox" name="off[]" value="{{ $p }}" @checked(in_array($p,$off))> {{ $p }}</label>@endforeach
  </div></div>
 <div><button class="btn btn-primary">Save</button></div>
</form>
@endcan
@if($byState->isNotEmpty())
<h3 class="font-display text-lg mb-2">State-wise</h3>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>State</th><th>Active members</th><th>Hidden</th><th>Phone visible</th></tr></thead><tbody>
@foreach($byState as $s)<tr><td>{{ $s->pradesh }}</td><td>{{ number_format($s->total) }}</td><td>{{ number_format($s->hidden) }}</td><td>{{ number_format($s->phone) }}</td></tr>@endforeach
</tbody></table></div>
@endif
@endsection
