@extends('layouts.public')
@section('title','Login')
@section('content')
<section class="mandala min-h-[70vh] grid place-items-center px-4 py-12">
  <form method="post" action="{{ route('login.post') }}" class="card w-full max-w-md p-6 md:p-8 fade-in">
    @csrf
    <div class="text-center mb-6"><div class="w-14 h-14 mx-auto rounded-full grid place-items-center text-white font-display text-2xl" style="background:linear-gradient(135deg,var(--saffron),var(--ink))">ॐ</div>
    <h1 class="font-display text-2xl mt-3">Member Login</h1><p class="text-sm" style="color:var(--muted)">Log in with email or mobile number</p></div>
    @error('login')<div class="mb-4 p-3 rounded-xl badge-bad text-sm">{{ $message }}</div>@enderror
    <label class="label">Email / Mobile</label><input class="input mb-4" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
    <label class="label">Password</label><input class="input mb-4" type="password" name="password" autocomplete="current-password" required>
    <label class="flex items-center gap-2 text-sm mb-5"><input type="checkbox" name="remember" class="accent-orange-600"> Remember me</label>
    <button class="btn btn-primary w-full">Login</button>
  </form>
</section>
@endsection
