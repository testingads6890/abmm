@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="hero rounded-3xl p-6 md:p-8 mb-6">
  <div class="relative z-10 flex flex-wrap justify-between gap-4 items-end">
    <div><div class="text-xs uppercase tracking-widest opacity-70">Operational Year {{ $operationalYear }}</div>
      <h2 class="font-display text-2xl md:text-3xl mt-1">Namaste, {{ auth()->user()->name }}</h2><p class="opacity-80 text-sm mt-1">President: {{ $sabhapati }}</p></div>
    <div class="rounded-2xl bg-white/10 border border-white/15 px-4 py-3 text-sm"><div class="opacity-70">Push (Firebase)</div><b class="text-xl">{{ $fcmReady ? "Connected" : "Not set" }}</b></div>
  </div>
</div>
<div class="card p-5 mb-4 grid lg:grid-cols-3 gap-4 items-center">
  <div><span class="badge badge-ok">Active</span><h3 class="font-display text-xl mt-2">{{ $org }}</h3><p class="text-sm" style="color:var(--muted)">President: <b>{{ $sabhapati }}</b> · Operational Year: <b>{{ $operationalYear }}</b></p></div>
  <div class="grid grid-cols-2 gap-3 text-center"><div class="rounded-xl p-3" style="background:var(--saffron-l)"><div class="stat-num">{{ number_format($members) }}</div><div class="text-xs mt-1">Total Members</div></div><div class="rounded-xl p-3" style="background:var(--ink-l)"><div class="stat-num">{{ number_format($families) }}</div><div class="text-xs mt-1">Total Family(s)</div></div></div>
  <div><div class="text-xs uppercase tracking-wider mb-2" style="color:var(--muted)">Committee Members — {{ $operationalYear }}</div><div class="grid grid-cols-4 gap-2 text-center">@foreach(['country'=>'Country','pradesh'=>'ABMM Pradesh','state'=>'State','district'=>'District'] as $k=>$l)<div class="rounded-xl p-2 border" style="border-color:var(--line)"><b class="text-lg">{{ $levels[$k] ?? 0 }}</b><div class="text-[10px] leading-tight">{{ $l }}</div></div>@endforeach</div></div>
</div>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
  @foreach([['Total Members',$members,'var(--saffron)'],['Total Families',$families,'var(--ink-2)'],['Committee',$committee,'#1f9d6b'],['Matrimony',$matrimony,'#b4458b'],['Businesses',$businesses,'#2c7fb8'],['Donations ₹',number_format($donations),'#c85e18'],['Unread msgs',$unread,'#d64545'],['Pradesh cmte',$levels['pradesh'] ?? 0,'#6a5acd']] as [$l,$v,$c])
    <div class="card p-4 border-l-4" style="border-left-color:{{ $c }}"><div class="stat-num">{{ is_numeric($v) ? number_format($v) : $v }}</div><div class="text-xs uppercase tracking-wider mt-2" style="color:var(--muted)">{{ $l }}</div></div>
  @endforeach
</div>
<div class="grid lg:grid-cols-2 gap-4 mt-6">
  <div class="card p-4"><h3 class="font-display text-lg mb-3">New families (12 months)</h3><div class="h-64"><canvas id="c1"></canvas></div></div>
  <div class="card p-4"><h3 class="font-display text-lg mb-3">State-wise families</h3><div class="h-64"><canvas id="c2"></canvas></div></div>
</div>
<div class="grid lg:grid-cols-3 gap-4 mt-6">
  <div class="card p-4 lg:col-span-2"><div class="flex justify-between mb-2"><h3 class="font-display text-lg">Last 8 families registered</h3><a class="btn btn-ghost btn-sm" href="{{ route('admin.families.index') }}">All</a></div>
    <div class="overflow-x-auto"><table class="tbl stack"><thead><tr><th>Name</th><th>Father/Husband</th><th>District</th><th>Contact</th><th>Members</th></tr></thead><tbody>
    @foreach($recent as $f)<tr><td data-l="Name"><a class="font-semibold underline" href="{{ route('admin.families.show',$f) }}">{{ $f->head_name }}</a></td><td data-l="Father">{{ $f->father_husband_name }}</td><td data-l="District">{{ $f->district }}</td><td data-l="Contact">{{ $f->mobile }}</td><td data-l="Members">{{ $f->member_count }}</td></tr>@endforeach</tbody></table></div></div>
  <div class="card p-4"><h3 class="font-display text-lg mb-3">Activity</h3>
    @foreach($activity as $a)<div class="flex justify-between text-sm py-2 border-b" style="border-color:var(--line)"><span>{{ $a->user?->name }} <span class="badge">{{ $a->action }}</span></span><span style="color:var(--muted)">{{ $a->created_at->format('d M h:i A') }}</span></div>@endforeach</div>
</div>
@endsection
@push('scripts')
<script>
const m=@json($monthly), s=@json($byState);
new Chart(c1,{type:'line',data:{labels:m.map(x=>x.m),datasets:[{data:m.map(x=>x.c),borderColor:'#e8772e',backgroundColor:'rgba(232,119,46,.18)',fill:true,tension:.35}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}}}});
new Chart(c2,{type:'bar',data:{labels:s.map(x=>x.state),datasets:[{data:s.map(x=>x.c),backgroundColor:'#2b3070',borderRadius:8}]},options:{maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}}}});
</script>
@endpush
