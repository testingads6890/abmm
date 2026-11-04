@extends('layouts.admin')
@section('title','Import exam questions')
@section('content')
<form method="post" action="{{ route('admin.academy.import.store') }}" class="card p-4 grid gap-3 max-w-3xl">@csrf
 <div class="text-sm opacity-80">Ek line = ek sawal:<br><code>segment | subject | question | option1;option2;option3;option4 | sahi option number | explanation</code><br>segment: school, college, business or family. Example:<br><code>school | GK | What is the capital of India? | Mumbai;Delhi;Jaipur | 2 | The capital is New Delhi.</code></div>
 @if($done)<div class="p-2 rounded bg-green-50 text-green-800">{{ $done['ok'] }} sawal import hue.@if($done['bad']) Ye lines galat thin: {{ implode(', ', $done['bad']) }}@endif</div>@endif
 <textarea class="input" name="rows" rows="14" required></textarea>
 @if($errors->any())<div class="text-red-600 text-sm">{{ $errors->first() }}</div>@endif
 <div><button class="btn btn-primary">Import</button> <a class="btn btn-ghost" href="{{ route('admin.manage','examq') }}">Question bank</a></div>
</form>
@endsection
