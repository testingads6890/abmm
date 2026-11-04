@extends('layouts.admin')
@section('title','Languages')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">One line for any language <code>English text = Translation</code> . Member app <code>GET /api/v1/m/translations/&lt;locale&gt;</code> downloads this (bundled <code>lang/&lt;locale&gt;.json</code> overrides it).</p>
<form method="get" class="flex gap-2 mb-3"><select class="input !w-40" name="locale" onchange="this.form.submit()">@foreach($locales as $l)<option @selected($locale===$l)>{{ $l }}</option>@endforeach</select><input class="input !w-40" name="new" placeholder="new code e.g. gu"><button class="btn btn-sm btn-ink">Open</button></form>
<form method="post" action="{{ route('admin.translations.save') }}" class="card p-5 grid gap-3">@csrf<input type="hidden" name="locale" value="{{ $locale }}">
 <label class="label">Locale: <b>{{ $locale }}</b></label><textarea class="input font-mono text-xs" name="text" rows="18">{{ $text }}</textarea><div><button class="btn btn-primary">Save</button></div></form>
@endsection
