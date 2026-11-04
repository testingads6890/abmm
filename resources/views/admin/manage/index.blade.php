@extends('layouts.admin')
@section('title',$res['title'])
@section('content')
@php $p=$res['perm']; @endphp
<div class="flex flex-wrap gap-2 items-center mb-3">
 <form method="get" class="flex flex-wrap gap-2">@foreach($res['filters'] ?? [] as $f)<select class="input !w-40" name="{{ $f }}" onchange="this.form.submit()"><option value="">{{ ucfirst(str_replace('_',' ',$f)) }}: all</option>@foreach($filterOptions[$f] ?? [] as $o)<option value="{{ $o }}" @selected(request($f)===(string)$o)>{{ $o }}</option>@endforeach</select>@endforeach</form>
 <span class="flex-1"></span>
 @if(!empty($res['export']))@foreach(['xlsx'=>'Excel','csv'=>'CSV','pdf'=>'PDF'] as $f=>$l)<a class="btn btn-sm" href="{{ request()->fullUrlWithQuery(['export'=>$f]) }}">{{ $l }}</a>@endforeach @endif
 @if(!empty($res['create']))@can($p.'.create')<a class="btn btn-sm btn-ink" href="{{ route('admin.manage.new',$key) }}">+ Add</a>@endcan @endif
</div>
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr>@foreach($res['cols'] as $l=>$c)<th>{{ $l }}</th>@endforeach<th></th></tr></thead><tbody>
@forelse($rows as $row)<tr>
 @foreach($res['cols'] as $l=>$c)@php $v=$row->$c ?? ''; @endphp<td>@if($c==='path' && $v)<a target="_blank" href="{{ \App\Support\MemberApp::photoUrl($v) }}">View</a>@elseif(is_bool($v) || in_array($c,['is_active','verified','seller_verified','is_featured','has_12a','has_80g','geo_targeting']))<span>{{ $v ? 'Yes':'No' }}</span>@else{{ \Illuminate\Support\Str::limit((string)$v,60) }}@endif</td>@endforeach
 <td class="whitespace-nowrap">
  @foreach($res['actions'] ?? [] as $name=>$a)
   @php $ok = (!isset($a['when']) || in_array($row->status ?? null,$a['when'],true)); foreach($a['only'] ?? [] as $oc=>$ov){ if((int)($row->$oc ?? 0)!==(int)$ov) $ok=false; } @endphp
   @if($ok && auth()->user()->can($p.'.'.($a['perm'] ?? 'edit')))<form class="inline" method="post" action="{{ route('admin.manage.act',[$key,$row->id,$name]) }}" @if(!empty($a['confirm']))onsubmit="return confirm('{{ $a['confirm'] }}')"@endif>@csrf<button class="btn btn-sm">{{ $a[0] }}</button></form>@endif
  @endforeach
  @if(!empty($res['form']) && empty($res['readonly']))@can($p.'.edit')<a class="btn btn-sm" href="{{ route('admin.manage.edit',[$key,$row->id]) }}">Edit</a>@endcan @endif
  @if(!empty($res['delete']))@can($p.'.delete')<form class="inline" method="post" action="{{ route('admin.manage.destroy',[$key,$row->id]) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm">Delete</button></form>@endcan @endif
 </td></tr>
@empty<tr><td colspan="{{ count($res['cols'])+1 }}" style="color:var(--muted)">Nothing found.</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div>
@endsection
