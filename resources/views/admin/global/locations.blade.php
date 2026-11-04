@extends('layouts.admin')
@section('title','Locations')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<p class="text-sm mb-3" style="color:var(--muted)">Structure: <b>Country → State → District → City → Zone 1, Zone 2 … → Pincode</b>. The area of sub-admins (State → District → City → Zone) is chosen from this tree.</p>

<div class="flex flex-wrap items-center gap-1 text-sm mb-4">
 <a href="{{ route('admin.locations') }}" class="underline">Countries</a>
 @foreach($crumbs as $c)<span>›</span>@if($loop->last)<b>{{ $c->name }}</b> <span style="color:var(--muted)">({{ $levels[$c->level] }})</span>@else<a class="underline" href="{{ route('admin.locations',['node'=>$c->id]) }}">{{ $c->name }}</a>@endif @endforeach
</div>
@if($stats)<div class="flex flex-wrap gap-2 mb-4 text-xs">@foreach($levels as $k=>$l)<span class="card px-3 py-1">{{ $l }}: <b>{{ $stats[$k] ?? 0 }}</b></span>@endforeach</div>@endif

<form method="get" class="flex gap-2 mb-3">@if($node)<input type="hidden" name="node" value="{{ $node->id }}">@endif<input class="input !w-56" name="q" value="{{ request('q') }}" placeholder="Search name / pincode"><button class="btn btn-sm">Search</button></form>

<div class="card p-2 overflow-x-auto mb-4"><table class="tbl"><thead><tr><th>Level</th><th>Name</th><th>{{ $node && in_array($node->level,['city','zone']) ? 'Code / Area' : 'Code' }}</th><th>Inside</th><th>Active</th><th></th></tr></thead><tbody>
@forelse($rows as $n)<tr><td>{{ $levels[$n->level] }}</td>
 <td>@if($n->level!=='pincode')<a class="underline" href="{{ route('admin.locations',['node'=>$n->id]) }}">{{ $n->name }}</a>@else{{ $n->name }}@endif</td><td>{{ $n->code }}</td><td>{{ $n->children_count }}</td>
 <td>@can('geography.edit')<form method="post" action="{{ route('admin.locations.toggle',$n) }}">@csrf<button class="btn btn-sm btn-ghost">{{ $n->is_active ? 'Yes':'No' }}</button></form>@else{{ $n->is_active ? 'Yes':'No' }}@endcan</td>
 <td class="whitespace-nowrap">@can('geography.edit')<details class="inline-block"><summary class="btn btn-sm btn-ghost cursor-pointer">Edit</summary><form method="post" action="{{ route('admin.locations.update',$n) }}" class="flex gap-1 mt-1">@csrf @method('PUT')<input class="input !w-40" name="name" value="{{ $n->name }}" required><input class="input !w-32" name="code" value="{{ $n->code }}" placeholder="code/area"><input class="input !w-16" type="number" name="sort" value="{{ $n->sort }}"><button class="btn btn-sm btn-primary">Save</button></form></details>@endcan
 @can('geography.delete')<form method="post" action="{{ route('admin.locations.destroy',$n) }}" class="inline" onsubmit="return confirm('Remove?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">✕</button></form>@endcan</td></tr>
@empty<tr><td colspan="6" class="text-center" style="color:var(--muted)">Nothing here yet - add below.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}

@can('geography.create')@if($childLevels)
<div class="grid lg:grid-cols-2 gap-4 mt-4">
<form method="post" action="{{ route('admin.locations.store') }}" class="card p-5 grid sm:grid-cols-2 gap-3">@csrf
 <h3 class="font-display text-lg col-span-full">{{ $node ? $node->name.' - add inside' : 'Add country' }}</h3>
 @if($node)<input type="hidden" name="parent_id" value="{{ $node->id }}">@endif
 <select class="input" name="level">@foreach($childLevels as $l)<option value="{{ $l }}">{{ $levels[$l] }}</option>@endforeach</select>
 <input class="input" name="name" placeholder="{{ $node && $node->level==='city' ? 'Zone 1 / North Zone / 302001' : 'Name' }}" required>
 <input class="input col-span-full" name="code" placeholder="Code (for pincode: area / locality name)">
 <div class="col-span-full"><button class="btn btn-primary">Add</button></div></form>
<form method="post" action="{{ route('admin.locations.import') }}" class="card p-5 grid gap-3">@csrf<h3 class="font-display text-lg">Bulk import</h3>
 <p class="text-xs" style="color:var(--muted)">Har line: <code>Country | State | District | City | Zone | Pincode(s) | Area</code>. Zone, Pincode, Area are optional. Instead of a zone <code>#5</code> to create Zone 1…Zone 5. Separate pincodes with commas.<br>Example: <code>India | Rajasthan | Jaipur | Jaipur | #4 | 302001, 302002 | Civil Lines</code></p>
 <textarea class="input" rows="6" name="bulk" required></textarea><div><button class="btn btn-primary">Import</button></div></form>
</div>@endif @endcan
@endsection
