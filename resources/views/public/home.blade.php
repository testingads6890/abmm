@extends('layouts.public')
@section('content')
<section class="hero">
  <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-5 gap-10 items-center relative z-10">
    <div class="md:col-span-3">
      <span class="badge" style="background:rgba(255,255,255,.15);color:#fff">जय महेश · Maheshwari Samaj</span>
      <h1 class="font-display text-4xl md:text-6xl mt-4 leading-[1.05]">One family, <span style="color:#ffb27a">the whole of India.</span></h1>
      <p class="mt-5 text-lg opacity-90 max-w-xl">{{ \App\Models\Setting::get('org_name') }} - a platform that brings family, committee, business and marriage together in one digital network.</p>
      <div class="mt-7 flex flex-wrap gap-3"><a href="{{ route('login') }}" class="btn btn-primary">Member Login</a><a href="{{ route('businesses') }}" class="btn" style="background:rgba(255,255,255,.12);color:#fff">Business Directory</a></div>
    </div>
    <div class="md:col-span-2 grid grid-cols-2 gap-3">
      @foreach([['families','Families'],['members','Members'],['districts','Districts'],['businesses','Businesses']] as [$k,$l])
        <div class="rounded-2xl p-4 bg-white/10 backdrop-blur border border-white/15"><div class="stat-num">{{ number_format($stats[$k]) }}</div><div class="text-xs mt-1 opacity-80 uppercase tracking-wider">{{ $l }}</div></div>
      @endforeach
    </div>
  </div>
</section>

<section class="max-w-7xl mx-auto px-4 mt-14">
  <h2 class="font-display text-3xl mb-6">National Committee</h2>
  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @forelse($leaders as $p)
      <div class="card p-5 text-center"><div class="w-20 h-20 mx-auto rounded-full grid place-items-center text-2xl font-display text-white" style="background:linear-gradient(135deg,var(--ink),var(--saffron))">{{ mb_substr($p->name,0,1) }}</div>
      <b class="block mt-3">{{ $p->name }}</b><span class="badge mt-1">{{ $p->designation }}</span></div>
    @empty <p style="color:var(--muted)">Committee coming soon.</p> @endforelse
  </div>
  <div class="mt-4"><a class="btn btn-ghost" href="{{ route('committee') }}">View the full committee →</a></div>
</section>

<section class="max-w-7xl mx-auto px-4 mt-14 grid lg:grid-cols-2 gap-8">
  <div>
    <h2 class="font-display text-3xl mb-6">Upcoming events</h2>
    <div class="grid gap-3">
    @forelse($events as $e)
      <div class="card p-4 flex gap-4 items-center"><div class="text-center rounded-xl px-3 py-2 text-white" style="background:var(--ink)"><div class="stat-num text-2xl">{{ $e->event_date->format('d') }}</div><div class="text-xs uppercase">{{ $e->event_date->format('M') }}</div></div>
      <div><b>{{ $e->title }}</b><div class="text-sm" style="color:var(--muted)">{{ $e->venue }}</div></div></div>
    @empty <p style="color:var(--muted)">No events yet.</p> @endforelse
    </div>
  </div>
  <div>
    <h2 class="font-display text-3xl mb-6">Community businesses</h2>
    <div class="grid sm:grid-cols-2 gap-3">
    @foreach($businesses as $b)
      <div class="card p-4"><span class="badge">{{ $b->type }}</span><b class="block mt-2">{{ $b->name }}</b><div class="text-sm" style="color:var(--muted)">{{ \Illuminate\Support\Str::limit($b->address, 50) }}</div></div>
    @endforeach
    </div>
  </div>
</section>

<section class="max-w-7xl mx-auto px-4 mt-14">
  <div class="card mandala p-8 md:p-12 text-center"><h2 class="font-display text-3xl">Contribute to society service</h2><p class="mt-2" style="color:var(--muted)">Every donation goes towards the development of the community.</p><a href="{{ route('donate') }}" class="btn btn-primary mt-5">Donate Now</a></div>
</section>
@endsection
