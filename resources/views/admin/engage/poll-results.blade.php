@extends('layouts.admin')
@section('title','Results: '.$p->title)
@section('content')
<div class="flex flex-wrap gap-2 mb-3 items-center"><span class="text-sm">Total responses: <b>{{ $total }}</b></span>
 @foreach(['xlsx'=>'Excel','csv'=>'CSV','pdf'=>'PDF'] as $f=>$l)<a class="btn btn-sm" href="{{ route('admin.polls.results',[$p->id,'export'=>$f]) }}">{{ $l }}</a>@endforeach</div>
<div class="grid lg:grid-cols-2 gap-4">
@foreach($agg as $a)<div class="card p-4"><h3 class="font-display text-lg mb-2">{{ $a['q']->text }} <span class="text-xs" style="color:var(--muted)">({{ $a['answered'] }} answers)</span></h3>
 @if(in_array($a['q']->type,['single','multi']))
  @foreach($a['tally'] as $opt=>$n)<div class="mb-1 text-sm">{{ $opt }} — {{ $n }} ({{ $a['answered'] ? round($n*100/max(1,$a['answered'])) : 0 }}%)<div style="background:var(--line,#eee);height:8px;border-radius:4px"><div style="background:var(--brand,#c0392b);height:8px;border-radius:4px;width:{{ $a['answered'] ? round($n*100/max(1,$a['answered'])) : 0 }}%"></div></div></div>@endforeach
 @elseif($a['q']->type==='rating')<div class="text-3xl font-display">{{ $a['avg'] ?? '—' }} / 5</div>@foreach($a['dist'] as $s=>$n)<div class="text-sm">{{ $s }}★ : {{ $n }}</div>@endforeach
 @else<ul class="text-sm list-disc pl-5">@foreach(array_slice($a['texts'],0,50) as $t)<li>{{ $t }}</li>@endforeach</ul>@endif</div>@endforeach
</div>
<div class="card p-3 mt-4 overflow-x-auto"><h3 class="font-display text-lg mb-2">Country / State wise</h3><table class="tbl"><thead><tr><th>Country</th><th>State</th><th>Responses</th></tr></thead><tbody>@foreach($byState as $x)<tr><td>{{ $x->country ?: '—' }}</td><td>{{ $x->state ?: '—' }}</td><td>{{ $x->c }}</td></tr>@endforeach</tbody></table></div>
@endsection
