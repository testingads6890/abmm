@extends('layouts.admin')
@section('title','Change password')
@section('content')
<div class="max-w-md mx-auto">
 @if(session('warn'))<div class="rounded-xl p-3 text-sm badge-warn mb-3">{{ __(session('warn')) }}</div>@endif
 @if($errors->any())<div class="rounded-xl p-3 text-sm badge-bad mb-3">{{ $errors->first() }}</div>@endif
 <form method="post" action="{{ route('admin.password') }}" class="card p-5 grid gap-3">@csrf
  <h3 class="font-display text-lg">Change password</h3>
  <input class="input" type="password" name="current_password" placeholder="Current password" autocomplete="current-password" required>
  <input class="input" type="password" name="password" placeholder="New password (min 10 characters)" autocomplete="new-password" minlength="10" required>
  <input class="input" type="password" name="password_confirmation" placeholder="Confirm new password" autocomplete="new-password" required>
  <button class="btn btn-primary">Save password</button>
 </form>
</div>
@endsection
