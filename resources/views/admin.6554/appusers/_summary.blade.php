@php $tf = collect($summary)->sum('fam'); $tm = collect($summary)->sum('mem'); $base = request()->routeIs('admin.appusers.members') ? 'admin.appusers.members' : 'admin.appusers.families'; @endphp
<div class="card p-4 mb-3">
 <div class="flex flex-wrap justify-between gap-2 mb-2"><b>{{ auth()->user()->isSuper() ? 'State-wise App Registrations' : 'App Registrations — ' . (auth()->user()->pradesh ?: 'your area') }}</b><span class="text-sm" style="color:var(--muted)">{{ number_format($tf) }} families · {{ number_format($tm) }} members</span></div>
 <div class="flex flex-wrap gap-2">
  <a class="badge {{ request('state') ? '' : 'badge-ok' }}" href="{{ route($base) }}">All ({{ number_format($tf) }})</a>
  @foreach($summary as $s)<a class="badge {{ request('state')===$s->st ? 'badge-ok' : '' }}" href="{{ route($base, ['state'=>$s->st]) }}" title="{{ $s->pr ? 'ABMM Pradesh: '.$s->pr : '' }}">{{ $s->st==='__none' ? 'Unmapped state' : $s->st }} · {{ number_format($s->fam) }} fam · {{ number_format($s->mem) }} mem</a>@endforeach
 </div>
</div>
