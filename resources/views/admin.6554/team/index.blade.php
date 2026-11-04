@extends('layouts.admin')
@section('title','Team · Sub-Admins')
@section('content')
<div class="flex flex-wrap justify-between items-center gap-3 mb-3">
  <nav class="text-sm" style="color:var(--muted)">Dashboard / <b>Team</b> <span class="badge">{{ $me->roleLabel() }}</span></nav>
  @can('users.create')@if($me->creatableRoles())<a class="btn btn-primary btn-sm" href="{{ route('admin.team.create') }}">+ Add {{ $me->isSuper() ? 'Sub-Admin' : collect($me->creatableRoles())->map(fn($r)=>config('permissions.roles.'.$r.'.label'))->implode(' / ') }}</a>@endif @endcan
</div>
<form class="card p-4 grid sm:grid-cols-4 gap-3 mb-4">
  <input class="input sm:col-span-2" name="q" value="{{ request('q') }}" placeholder="Name, mobile or email">
  @if($me->isSuper())
  <select class="input" name="role"><option value="">All roles</option>@foreach(collect(config('permissions.roles'))->except('member')->map(fn($x)=>$x['label'])->all() as $k=>$l)<option value="{{ $k }}" @selected(request('role')===$k)>{{ $l }}</option>@endforeach</select>
  @endif
  <div class="flex gap-2"><button class="btn btn-ink flex-1">Search</button><a class="btn btn-ghost" href="{{ route('admin.team.index') }}">Reset</a></div>
</form>
<div class="card overflow-x-auto"><table class="tbl w-full text-sm"><thead><tr><th>Name</th><th>Role</th><th>Area</th><th>Mobile</th><th>Created</th><th>Last login</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($rows as $u)
<tr>
 <td data-l="Name"><b>{{ $u->name }}</b><div class="text-xs" style="color:var(--muted)">{{ $u->email }}</div></td>
 <td data-l="Role"><span class="badge">{{ $u->roleLabel() }}</span></td>
 <td data-l="Area">{{ $u->country ? $u->country.' › ' : '' }}{{ $u->pradesh ?: ($u->country ? '' : '—') }}@if($u->district) › {{ $u->district }}@endif @if($u->city) › {{ $u->city }}@endif @if($u->zone) › {{ $u->zone }}@endif</td>
 <td data-l="Mobile">{{ $u->mobile }}</td>
 <td data-l="Banaya">{{ $u->creator?->name ?? '—' }}</td>
 <td data-l="Last login">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
 <td data-l="Status"><span class="badge {{ $u->is_active ? 'badge-ok' : '' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
 <td data-l="Actions"><div class="flex flex-wrap gap-1">
  @can('users.edit')<a class="btn btn-ghost btn-sm" href="{{ route('admin.team.edit',$u) }}">Edit / Permissions</a>@endcan
  @can('users.toggle')<form method="post" action="{{ route('admin.team.toggle',$u) }}">@csrf @method('PATCH')<button class="btn btn-ghost btn-sm">{{ $u->is_active ? 'Disable' : 'Enable' }}</button></form>@endcan
  @can('users.reset_password')<form method="post" action="{{ route('admin.team.reset',$u) }}" onsubmit="return confirm('A new temporary password will be created and the user will be logged out. Continue?')">@csrf<button class="btn btn-ghost btn-sm">Reset password</button></form>@endcan
 </div></td>
</tr>
@empty<tr><td colspan="8" class="text-center py-8" style="color:var(--muted)">No users yet.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
