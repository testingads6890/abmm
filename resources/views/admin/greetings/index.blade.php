@extends('layouts.admin')
@section('title','Birthday & Anniversary')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">Members whose birthday / wedding anniversary is today automatically get a push + notification at 7 am. Below is today's list for your area.</p>
<div class="grid sm:grid-cols-3 gap-3 mb-4">
 <div class="card p-4"><div class="text-sm" style="color:var(--muted)">Birthdays today (your area)</div><div class="text-3xl font-display">{{ $lists['birthday']->count() }}</div></div>
 <div class="card p-4"><div class="text-sm" style="color:var(--muted)">Anniversaries today (your area)</div><div class="text-3xl font-display">{{ $lists['anniversary']->count() }}</div></div>
 <div class="card p-4"><div class="text-sm" style="color:var(--muted)">Sent today / member wishes</div><div class="text-3xl font-display">{{ ($sentToday['birthday'] ?? 0) + ($sentToday['anniversary'] ?? 0) }} / {{ $wishesToday }}</div></div>
</div>

@can('engagement.edit')
<form method="post" action="{{ route('admin.greetings.settings') }}" class="card p-5 grid gap-3 mb-4">@csrf
 <h3 class="font-display text-lg">Settings (Super Admin)</h3>
 <div class="grid sm:grid-cols-2 gap-4">
  <div><label class="flex gap-2 text-sm mb-1"><input type="checkbox" name="birthday_on" value="1" @checked($on['birthday'])> Birthday greeting on</label>
   <label class="label">Birthday message ({name} = member name)</label><textarea class="input" rows="3" name="birthday_text" required>{{ old('birthday_text',$text['birthday']) }}</textarea></div>
  <div><label class="flex gap-2 text-sm mb-1"><input type="checkbox" name="anniversary_on" value="1" @checked($on['anniversary'])> Anniversary greeting on</label>
   <label class="label">Anniversary message</label><textarea class="input" rows="3" name="anniversary_text" required>{{ old('anniversary_text',$text['anniversary']) }}</textarea></div>
 </div>
 <div><label class="label">Keep greetings OFF in these states (tick = off)</label>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-1 max-h-48 overflow-y-auto border rounded p-2">
   @foreach($pradeshList as $p)<label class="flex gap-2 text-sm"><input type="checkbox" name="off[]" value="{{ $p }}" @checked(in_array($p,$off))> {{ $p }}</label>@endforeach
  </div></div>
 <div class="flex gap-2"><button class="btn btn-primary">Save</button></div>
</form>
<form method="post" action="{{ route('admin.greetings.send') }}" class="mb-4" onsubmit="return confirm('Send to today\'s celebrants now?')">@csrf<button class="btn btn-ink">Send today's greetings now</button> <span class="text-xs" style="color:var(--muted)">(sent only once a year to a member)</span></form>
@endcan

@foreach(['birthday'=>'Today\'s Birthdays','anniversary'=>'Today\'s Anniversaries'] as $k=>$label)
<h3 class="font-display text-lg mb-2">{{ $label }}</h3>
<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Name</th><th>Family ID</th><th>{{ $k==='birthday' ? 'Age' : 'Years' }}</th><th>District</th><th>Mobile</th></tr></thead><tbody>
@forelse($lists[$k] as $m)<tr><td>{{ trim($m->name.' '.$m->surname) }}</td><td>{{ $m->family?->family_code }}</td>
 <td>{{ $k==='birthday' ? ($m->dob ? now()->year - $m->dob->year : '') : ($m->anniversary ? now()->year - $m->anniversary->year : '') }}</td><td>{{ $m->family?->district }}</td><td>{{ $m->mobile }}</td></tr>
@empty<tr><td colspan="5" class="text-center" style="color:var(--muted)">No one today.</td></tr>@endforelse
</tbody></table></div>
@endforeach
@endsection
