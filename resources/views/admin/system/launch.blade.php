@extends('layouts.admin')
@section('title','Launch readiness')
@section('content')
<div class="card p-4 mb-4 flex flex-wrap items-center gap-4">
 <div class="text-lg font-semibold">{{ $sum['ready'] ? '✅ Ready to go live (review the warnings)' : '⛔ Not ready - fix the red items' }}</div>
 <div class="text-sm opacity-80">OK {{ $sum['ok'] }} · Warnings {{ $sum['warn'] }} · Failures {{ $sum['fail'] }}</div>
 <a class="btn btn-ghost btn-sm" href="{{ route('admin.system.launch') }}">Re-check</a>
</div>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Check</th><th>Status</th><th>Detail</th><th>How to fix</th></tr></thead><tbody>
@foreach($checks as $c)<tr>
 <td class="font-medium">{{ $c['name'] }}</td>
 <td><span class="px-2 py-0.5 rounded text-xs font-semibold {{ ['ok'=>'bg-green-100 text-green-800','warn'=>'bg-amber-100 text-amber-800','fail'=>'bg-red-100 text-red-800'][$c['status']] }}">{{ strtoupper($c['status']) }}</span></td>
 <td>{{ $c['detail'] }}</td><td class="text-sm opacity-80">{{ $c['status']==='ok' ? '' : $c['fix'] }}</td></tr>@endforeach
</tbody></table></div>
@endsection
