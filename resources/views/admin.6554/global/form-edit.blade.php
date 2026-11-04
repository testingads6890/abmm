@extends('layouts.admin')
@section('title', $f->exists ? 'Edit form' : 'New form')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="post" action="{{ $f->exists ? route('admin.forms.update',$f) : route('admin.forms.store') }}" class="card p-5 grid sm:grid-cols-2 gap-3">@csrf @if($f->exists) @method('PUT') @endif
 <div><label class="label">Title</label><input class="input" name="title" value="{{ old('title',$f->title) }}" required></div>
 <div><label class="label">Slug (URL key)</label><input class="input" name="slug" value="{{ old('slug',$f->slug) }}" required placeholder="scholarship-2026"></div>
 <div class="sm:col-span-2"><label class="label">Description</label><textarea class="input" name="description" rows="2">{{ old('description',$f->description) }}</textarea></div>
 <div class="sm:col-span-2"><label class="label">Fields — ek line me ek: <code>key | Label | type | required(1/0) | options</code></label>
  <textarea class="input font-mono text-xs" name="fields_text" rows="9" required>{{ old('fields_text', \App\Http\Controllers\Admin\FormBuilderController::fieldsText($f->fields ?? [])) }}</textarea>
  <small style="color:var(--muted)">Types: {{ implode(', ', array_keys(\App\Models\FormDefinition::TYPES)) }}. Example: <code>course | Course | select | 1 | BCom,BSc,BE</code></small></div>
 <div><label class="label">Fee (0 = free)</label><input class="input" type="number" step="0.01" name="fee" value="{{ old('fee',$f->fee ?? 0) }}" required></div>
 <div><label class="label">Currency</label><input class="input" name="currency" maxlength="3" value="{{ old('currency',$f->currency) }}" required></div>
 <div><label class="label">Only for country (optional)</label><input class="input" name="country" value="{{ old('country',$f->country) }}"></div>
 <div><label class="label">Only for city (optional)</label><input class="input" name="city" value="{{ old('city',$f->city) }}"></div>
 <label class="text-sm flex items-center gap-2"><input type="checkbox" name="needs_approval" value="1" @checked(old('needs_approval',$f->needs_approval))> Admin approval required</label>
 <label class="text-sm flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$f->is_active))> Active (visible in app)</label>
 <div class="sm:col-span-2 flex gap-2"><button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="{{ route('admin.forms.index') }}">Cancel</a></div>
</form>
@endsection
