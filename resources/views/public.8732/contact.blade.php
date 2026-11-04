@extends('layouts.public')
@section('title','Contact')
@section('content')
<div class="max-w-5xl mx-auto px-4 pt-10 grid md:grid-cols-2 gap-8">
  <div><h1 class="font-display text-4xl">Contact</h1>
    <p class="mt-3" style="color:var(--muted)">{{ \App\Models\Setting::get('contact_address') }}</p>
    <p class="mt-2">📞 {{ \App\Models\Setting::get('contact_mobile') }}<br>✉ {{ \App\Models\Setting::get('contact_email') }}</p>
    <div class="grid gap-3 mt-5">@foreach($offices as $o)<div class="card p-4"><span class="badge">{{ $o->title }}</span>@if($o->person)<b class="block mt-2">{{ $o->person }}</b>@endif<p class="text-sm mt-1" style="color:var(--muted)">{{ $o->address }}</p><p class="text-sm mt-1">{{ $o->phones }} {{ $o->email }}</p></div>@endforeach</div></div>
  <form method="post" action="{{ route('contact.store') }}" class="card p-6 grid gap-4">@csrf
    @if(session('ok'))<div class="p-3 rounded-xl badge-ok text-sm">{{ __(session('ok')) }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-xl badge-bad text-sm">{{ $errors->first() }}</div>@endif
    <div><label class="label">Name</label><input class="input" name="name" required value="{{ old('name') }}"></div>
    <div class="grid grid-cols-2 gap-3"><div><label class="label">Mobile</label><input class="input" name="mobile" value="{{ old('mobile') }}"></div><div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email') }}"></div></div>
    <div><label class="label">Message</label><textarea class="input" rows="4" name="message" required>{{ old('message') }}</textarea></div>
    <button class="btn btn-primary">Send</button></form>
</div>
@endsection
