@extends('layouts.admin')
@section('title','Smart search')
@section('content')
<form method="get" class="card p-4 flex flex-wrap gap-2 mb-4">
 <input class="input flex-1 min-w-[16rem]" name="q" value="{{ $q }}" placeholder='e.g. "CA ladka Jaipur 28 saal veg"  /  "Rathi Pune"  /  mobile / family ID  /  "accountant part time"' autofocus>
 <select class="input !w-auto" name="module"><option value="all">All modules</option>@foreach($modules as $k=>$m)<option value="{{ $k }}" @selected($module===$k)>{{ $m[0] }}</option>@endforeach</select>
 <button class="btn btn-primary">Search</button>
</form>
@if($q!=='')
 @if($understood)<div class="text-sm mb-3 opacity-80">Samjha gaya: @foreach($understood as $k=>$v)<span class="inline-block px-2 py-0.5 rounded bg-black/5 mr-1">{{ $k }}: {{ is_array($v) ? implode(', ',$v) : $v }}</span>@endforeach @if($aiOn)<span title="Claude assisted">✦ AI</span>@endif</div>@endif
 <div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Module</th><th>Result</th><th>Details</th><th></th></tr></thead><tbody>
 @forelse($results as $x)<tr><td>{{ $x['module'] }}</td><td>{{ $x['title'] }}</td><td>{{ $x['sub'] }}</td><td><a class="btn btn-sm btn-ghost" href="{{ $x['url'] }}">Open →</a></td></tr>
 @empty<tr><td colspan="4" class="opacity-70">Nothing found. Try another word.</td></tr>@endforelse</tbody></table></div>
@else<div class="opacity-70 text-sm">Write name, mobile, family ID, city or profession - only data from your area is shown.</div>@endif
@endsection
