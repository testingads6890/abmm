@extends('layouts.admin')
@section('title',$title)
@section('content')
<nav class="text-sm mb-3" style="color:var(--muted)">Dashboard / Reports / <b>{{ $title }}</b></nav>
@include('admin.reports._filters')
<p class="text-xs mb-2 no-print" style="color:var(--bad)">Note: By default application will export limited records. For more records please use at least one above filter.</p>
@if($note)<p class="text-base font-semibold mb-3 p-2 rounded-lg" style="background:#fff44f;color:#b00020">Note: {{ $note }}</p>@endif
<div class="flex justify-between text-sm mb-2"><span>Total Records : <b>{{ number_format($rows->total()) }}</b> <span style="color:var(--muted)">(100 records are shown by default; use filters or export for more)</span></span>
 <form class="no-print">@foreach(request()->except('per','page') as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach Show <select name="per" class="input !inline-block !w-auto !min-h-[34px] !py-1" onchange="this.form.submit()">@foreach([10,25,50,100] as $n)<option @selected(request('per',100)==$n)>{{ $n }}</option>@endforeach</select></form></div>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr>@foreach($cols as $l=>$c)<th>{{ $l }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($cols as $l=>$c)@php $v = is_callable($c) ? $c($row) : $row->{$c}; @endphp<td data-l="{{ $l }}">@if($v instanceof \Illuminate\Support\HtmlString){!! $v !!}@else{!! nl2br(e($v)) !!}@endif</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($cols) }}" class="py-8 text-center" style="color:var(--muted)">No records. Apply a filter and press Search.</td></tr>@endforelse</tbody></table></div>
<div class="mt-4 no-print">{{ $rows->links() }}</div>
@endsection
