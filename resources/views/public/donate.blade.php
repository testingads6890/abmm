@extends('layouts.public')
@section('title','Donate')
@section('content')
<div class="max-w-2xl mx-auto px-4 pt-10"><h1 class="font-display text-4xl">Donate Now</h1>
<form method="post" action="{{ route('donate.store') }}" class="card p-6 mt-5 grid gap-4">@csrf
  @if(session('ok'))<div class="p-3 rounded-xl badge-ok text-sm">{{ __(session('ok')) }}</div>@endif
  @if($errors->any())<div class="p-3 rounded-xl badge-bad text-sm">{{ $errors->first() }}</div>@endif
  <div class="flex flex-wrap gap-2">@foreach([501,1100,2100,5100] as $a)<button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('amt').value={{ $a }}">₹{{ number_format($a) }}</button>@endforeach</div>
  <div><label class="label">Amount (INR)</label><input id="amt" class="input" type="number" name="amount" min="10" required value="{{ old('amount') }}"></div>
  <div><label class="label">Receipt name</label><input class="input" name="name" required value="{{ old('name') }}"></div>
  <div class="grid sm:grid-cols-2 gap-3"><div><label class="label">Email</label><input class="input" type="email" name="email" required value="{{ old('email') }}"></div><div><label class="label">Mobile</label><input class="input" name="mobile" required maxlength="10" value="{{ old('mobile') }}"></div></div>
  <div class="grid sm:grid-cols-2 gap-3"><div><label class="label">Family ID (optional)</label><input class="input" name="family_code"></div><div><label class="label">City (optional)</label><input class="input" name="city"></div></div>
  <div><label class="label">PAN (Optional)</label><input class="input uppercase" name="pan" maxlength="10" value="{{ old('pan') }}"></div>
  <label class="flex gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="accent-orange-600 mt-1" required> I have read the <a class="underline" href="{{ route('page','terms-and-conditions') }}">Term &amp; Condition</a> and <a class="underline" href="{{ route('page','privacy-policy') }}">Privacy Policy</a>.</label>
  <label class="flex gap-2 text-sm"><input type="checkbox" name="agree" value="1" class="accent-orange-600 mt-1"> I understand that the donation is non-refundable (<a class="underline" href="{{ route('page','refund-policy') }}">Refund Policy</a>).</label>
  <button class="btn btn-primary">Donate</button></form></div>
@endsection
