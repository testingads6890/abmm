<!doctype html>
<html lang="hi" class="scroll-smooth">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'ABMM Sampark') · {{ \App\Models\Setting::get('org_name', 'Akhil Bharatvarshiya Maheshwari Mahasabha') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/abmm.css') }}">
@if(file_exists(public_path('css/tw.css')))<link rel="stylesheet" href="{{ asset('css/tw.css') }}?v={{ filemtime(public_path('css/tw.css')) }}">@else<script src="https://cdn.tailwindcss.com"></script>@endif
<script>try{if(localStorage.theme==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
</head>
<body class="min-h-screen flex flex-col">
<header class="sticky top-0 z-40 backdrop-blur bg-[color:var(--bg)]/85 border-b" style="border-color:var(--line)">
  <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
    <a href="{{ route('home') }}" class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-full grid place-items-center text-white font-display text-lg" style="background:linear-gradient(135deg,var(--saffron),var(--ink))">ॐ</span>
      <span class="leading-tight"><b class="font-display text-lg block">ABMM Sampark</b><small class="text-[11px]" style="color:var(--muted)">Maheshwari Mahasabha</small></span>
    </a>
    <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
      @foreach(['home'=>'Home','committee'=>'Committee','businesses'=>'Business','events'=>'Events','contact'=>'Contact'] as $r=>$l)
        <a href="{{ route($r) }}" class="px-3 py-2 rounded-full hover:bg-[color:var(--saffron-l)] {{ request()->routeIs($r) ? 'bg-[color:var(--saffron-l)]' : '' }}">{{ $l }}</a>
      @endforeach
    </nav>
    <div class="flex items-center gap-2">
      <a href="{{ route('donate') }}" class="btn btn-primary btn-sm hidden sm:inline-flex">Donate</a>
      @auth <a href="{{ route('admin.dashboard') }}" class="btn btn-ink btn-sm">Dashboard</a>
      @else <a href="{{ route('login') }}" class="btn btn-ink btn-sm">Login</a> @endauth
      <button class="md:hidden btn btn-ghost btn-sm" aria-label="Menu" onclick="document.getElementById('mnav').classList.toggle('hidden')">☰</button>
    </div>
  </div>
  <div id="mnav" class="hidden md:hidden border-t px-4 py-3 grid gap-1" style="border-color:var(--line)">
    @foreach(['home'=>'Home','committee'=>'Committee','businesses'=>'Business','events'=>'Events','contact'=>'Contact','donate'=>'Donate'] as $r=>$l)
      <a href="{{ route($r) }}" class="px-3 py-3 rounded-xl hover:bg-[color:var(--saffron-l)]">{{ $l }}</a>
    @endforeach
  </div>
</header>
<main class="flex-1">@yield('content')</main>
<footer class="mt-16 hero">
  <div class="max-w-7xl mx-auto px-4 py-10 grid gap-8 md:grid-cols-3 text-sm relative z-10">
    <div><b class="font-display text-xl">{{ \App\Models\Setting::get('org_name') }}</b><p class="mt-2 opacity-80">{{ \App\Models\Setting::get('contact_address') }}</p></div>
    <div><b>Quick links</b><div class="mt-2 grid gap-1 opacity-90">
      <a href="{{ route('page','privacy-policy') }}">Privacy Policy</a><a href="{{ route('page','terms-and-conditions') }}">Terms &amp; Conditions</a><a href="{{ route('page','refund-policy') }}">Refund Policy</a><a href="{{ route('contact') }}">Contact Us</a></div></div>
    <div><b>Contact</b><p class="mt-2 opacity-90">{{ \App\Models\Setting::get('contact_mobile') }}<br>{{ \App\Models\Setting::get('contact_email') }}</p></div>
  </div>
  <div class="relative z-10 text-center text-xs py-4 border-t border-white/10 opacity-80">© {{ date('Y') }} {{ \App\Models\Setting::get('copyright') }}</div>
</footer>
</body></html>
