@extends('layouts.admin')
@section('title',$title)
@section('content')
<nav class="text-sm mb-3" style="color:var(--muted)">Dashboard / Reports / <b>{{ $title }}</b></nav>
<p class="text-sm mb-3" style="color:var(--muted)">NOTE: Click GREEN buttons to download report in excel sheet.</p>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
@foreach(range(1,25) as $n)
  <div class="card p-4 {{ $size===$n ? 'ring-2 ring-orange-500' : '' }}"><div class="text-xs uppercase tracking-wider" style="color:var(--muted)">{{ $n }} member{{ $n>1?'s':'' }}</div>
   <div class="stat-num my-2">{{ number_format($counts[$n] ?? 0) }}</div><div class="text-xs mb-3" style="color:var(--muted)">Total Families</div>
   <div class="flex gap-2"><a class="btn btn-sm flex-1" style="background:var(--ok);color:#fff" href="{{ route('admin.reports','size') }}?size={{ $n }}">Click To See Records</a><a class="btn btn-sm" style="background:var(--ok);color:#fff" title="Excel (.xlsx)" href="{{ route('admin.reports','size') }}?size={{ $n }}&export=xlsx">⇩</a></div></div>
@endforeach
</div>
@if($rows)
<h3 class="font-display text-xl mt-8 mb-3">{{ $size }} families with this many members ({{ $rows->total() }})</h3>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Family ID</th><th>Head</th><th>Mobile</th><th>District</th><th>State</th><th></th></tr></thead><tbody>
@foreach($rows as $f)<tr><td data-l="ID">{{ $f->family_code }}</td><td data-l="Head">{{ $f->surname }} {{ $f->head_name }}</td><td data-l="Mobile">{{ $f->mobile }}</td><td data-l="District">{{ $f->district }}</td><td data-l="State">{{ $f->state }}</td><td><a class="btn btn-ghost btn-sm" href="{{ route('admin.families.show',$f) }}">View</a></td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $rows->links() }}</div>
@endif
<div class="mt-6 no-print flex gap-2 justify-center"><button class="btn btn-ghost" onclick="print()">🖨 Print</button><a class="btn btn-ghost" href="{{ route('admin.reports','size') }}?export=xlsx">Excel</a><a class="btn btn-ghost" href="{{ route('admin.reports','size') }}?export=pdf">PDF</a></div>
@endsection
