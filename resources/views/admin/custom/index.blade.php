@extends('layouts.admin')
@section('title','Form Fields')
@section('content')
@use('App\Support\CustomForm')
@if($errors->any())<div class="card p-3 mb-3 text-sm" style="border-color:#c0392b">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="flex flex-wrap gap-2 mb-4">@foreach(CustomForm::FORMS as $k=>$l)<a class="btn btn-sm {{ $form===$k?'btn-ink':'btn-ghost' }}" href="{{ route('admin.customfields',['form'=>$k]) }}">{{ $l }}</a>@endforeach</div>
<p class="text-sm mb-3" style="color:var(--muted)">Any new field you create here <b>{{ CustomForm::FORMS[$form] }}</b> will appear automatically in its add/edit form (also in the Trust list, PDF and department email). For dropdowns, write one option per line.</p>
<div class="grid lg:grid-cols-3 gap-4">
<div class="card p-2 lg:col-span-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Order</th><th>Label</th><th>Type / Options</th>@if($form==='trust')<th>Trust Category</th>@endif<th>Required</th><th>In list</th><th>Active</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $f)<tr>
 <td data-l="Order"><input class="input" style="width:5rem" form="f{{ $f->id }}" type="number" name="sort" value="{{ $f->sort }}"></td>
 <td data-l="Label"><input class="input" form="f{{ $f->id }}" name="label" value="{{ $f->label }}" required><div class="text-xs" style="color:var(--muted)">key: {{ $f->key }}</div></td>
 <td data-l="Type"><select class="input" form="f{{ $f->id }}" name="type">@foreach(CustomForm::TYPES as $tk=>$tl)<option value="{{ $tk }}" @selected($f->type===$tk)>{{ $tl }}</option>@endforeach</select>
   <textarea class="input mt-1" rows="2" form="f{{ $f->id }}" name="options" placeholder="Dropdown options (ek line = ek option)">{{ $f->options }}</textarea></td>
 @if($form==='trust')<td data-l="Category"><select class="input" form="f{{ $f->id }}" name="category"><option value="">All categories</option>@foreach($cats as $c)<option @selected($f->category===$c)>{{ $c }}</option>@endforeach</select></td>@endif
 <td data-l="Required"><input form="f{{ $f->id }}" type="checkbox" name="required" value="1" @checked($f->required) class="accent-orange-600"></td>
 <td data-l="In list"><input form="f{{ $f->id }}" type="checkbox" name="show_in_list" value="1" @checked($f->show_in_list) class="accent-orange-600"></td>
 <td data-l="Active"><input form="f{{ $f->id }}" type="checkbox" name="is_active" value="1" @checked($f->is_active) class="accent-orange-600"></td>
 <td class="whitespace-nowrap">@can('customfields.edit')<form id="f{{ $f->id }}" class="inline" method="post" action="{{ route('admin.customfields.update',$f) }}">@csrf @method('PUT')<button class="btn btn-ghost btn-sm">Save</button></form>@endcan
  @can('customfields.delete')<form class="inline" method="post" action="{{ route('admin.customfields.destroy',$f) }}" onsubmit="return confirm('Field hataayein? Purana saved data records me rahega.')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">✕</button></form>@endcan</td></tr>
@empty<tr><td colspan="8" class="py-6" style="color:var(--muted)">This form has no extra fields yet.</td></tr>@endforelse</tbody></table></div>
@can('customfields.create')
<form method="post" action="{{ route('admin.customfields.store') }}" class="card p-5 grid gap-3 content-start">@csrf<h3 class="font-display text-lg">New field - {{ CustomForm::FORMS[$form] }}</h3>
 <input type="hidden" name="form" value="{{ $form }}">
 <div><label class="label">Label *</label><input class="input" name="label" required maxlength="120" placeholder="e.g. GST Number"></div>
 <div><label class="label">Type</label><select class="input" name="type" id="nfType" onchange="document.getElementById('nfOpt').style.display=this.value==='select'?'':'none'">@foreach(CustomForm::TYPES as $tk=>$tl)<option value="{{ $tk }}">{{ $tl }}</option>@endforeach</select></div>
 @if($form==='trust')<div><label class="label">For which Trust Category</label><select class="input" name="category"><option value="">All categories</option>@foreach($cats as $c)<option>{{ $c }}</option>@endforeach</select></div>@endif
 <div id="nfOpt" style="display:none"><label class="label">Dropdown options (ek line = ek option)</label><textarea class="input" rows="4" name="options"></textarea></div>
 <label class="text-sm flex gap-2"><input type="checkbox" name="required" value="1" class="accent-orange-600"> Required</label>
 <label class="text-sm flex gap-2"><input type="checkbox" name="show_in_list" value="1" class="accent-orange-600"> Show as a column in the list/table</label>
 <input type="hidden" name="is_active" value="1">
 <button class="btn btn-primary">Add field</button></form>
@endcan
</div>
@endsection
