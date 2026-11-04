@extends('layouts.admin')
@section('title','Departments')
@section('content')
@if($errors->any())<div class="card p-3 mb-3 text-sm" style="border-color:#c0392b">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="grid lg:grid-cols-3 gap-4"><div class="card p-2 lg:col-span-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Department</th><th>Email (the submission goes here)</th><th>Fee ₹ (0 = free)</th><th>Active</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $d)<tr>
 <td data-l="Department"><input class="input" form="u{{ $d->id }}" name="name" value="{{ $d->name }}" required></td><td data-l="Email"><input class="input" form="u{{ $d->id }}" type="email" name="email" value="{{ $d->email }}" required></td>
 <td data-l="Fee"><input class="input" form="u{{ $d->id }}" type="number" step="0.01" min="0" name="fee" value="{{ $d->fee }}" required></td><td data-l="Active"><input form="u{{ $d->id }}" type="checkbox" name="is_active" value="1" @checked($d->is_active) class="accent-orange-600"></td>
 <td class="whitespace-nowrap"><form id="u{{ $d->id }}" class="inline" method="post" action="{{ route('admin.departments.update',$d) }}">@csrf @method('PUT')<button class="btn btn-ghost btn-sm">Save</button></form>
 <form class="inline" method="post" action="{{ route('admin.departments.destroy',$d) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">✕</button></form></td></tr>
@empty<tr><td colspan="5" class="py-6" style="color:var(--muted)">No departments.</td></tr>@endforelse</tbody></table></div>
<form method="post" action="{{ route('admin.departments.store') }}" class="card p-5 grid gap-3 content-start">@csrf<h3 class="font-display text-lg">New department</h3>
 <input class="input" name="name" placeholder="Department name" required><input class="input" type="email" name="email" placeholder="Department email" required><input class="input" type="number" step="0.01" min="0" name="fee" value="0" placeholder="Fee ₹"><button class="btn btn-primary">Add</button></form></div>
@endsection
