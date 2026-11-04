<!doctype html>
@php
  $u = auth()->user();
  $bell = \App\Models\UserNotification::where('user_id', $u->id)->latest()->limit(6)->get();
  $unread = \App\Models\UserNotification::where('user_id', $u->id)->whereNull('read_at')->count();
  $reports = \App\Http\Controllers\Admin\ReportController::TYPES;
  $org = \App\Models\Setting::get('org_name', 'Akhil Bharatvarshiya Maheshwari Mahasabha');
@endphp
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Dashboard') · ABMM Sampark</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="{{ asset('css/abmm.css') }}">
@if(file_exists(public_path('css/tw.css')))<link rel="stylesheet" href="{{ asset('css/tw.css') }}?v={{ filemtime(public_path('css/tw.css')) }}">@else<script src="https://cdn.tailwindcss.com"></script>@endif
<script>try{if(localStorage.theme==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
</head>
<body>
<script>try{if(localStorage.collapsed==='1')document.body.classList.add('collapsed');if(localStorage.hideimg==='1')document.body.classList.add('hide-img')}catch(e){}</script>
<div class="shell min-h-screen">
  {{-- ============ SIDEBAR (same order as the original system) ============ --}}
  <aside id="side" class="side fixed lg:sticky top-0 z-50 h-screen w-72 lg:w-auto -translate-x-full lg:translate-x-0 transition-transform overflow-y-auto p-3">
    <div class="flex items-center justify-between px-2 pb-3">
      <a href="{{ route('home') }}" class="flex items-center gap-3 text-white"><span class="avatar w-10 h-10 shrink-0 rounded-full grid place-items-center font-display" style="background:var(--saffron)">ॐ</span><b class="brand-t font-display text-lg">ABMM Sampark</b></a>
      <button class="lg:hidden text-white text-xl" onclick="toggleSide()">✕</button>
    </div>
    @php $L = fn($r,$ico,$label,$match=null) => '<a href="'.e(route($r)).'" class="'.(request()->routeIs($match ?? $r) ? 'active' : '').'" title="'.e(__($label)).'"><span class="w-5 text-center">'.$ico.'</span><span class="lbl">'.e(__($label)).'</span></a>'; @endphp
    {!! $L('admin.dashboard','◈','Dashboard') !!}
    @can('families.view'){!! $L('admin.families.index','⌂','Member','admin.families.*') !!}@endcan
    @can('families.view'){!! $L('admin.members.import','⇅','Import / Export Members') !!}@endcan
    @can('appusers.view')
    <details {{ request()->routeIs('admin.appusers.*') ? 'open' : '' }}>
      <summary><span class="w-5 text-center">☎</span><span class="lbl">{{ __('Mobile App') }}</span><span class="chev ml-auto">›</span></summary>
      <div class="sub">
        <a href="{{ route('admin.appusers.families') }}" class="{{ request()->routeIs('admin.appusers.families') ? 'active' : '' }}">{{ __('Registered Families') }}</a>
        <a href="{{ route('admin.appusers.members') }}" class="{{ request()->routeIs('admin.appusers.members') ? 'active' : '' }}">{{ __('Registered Members') }}</a>
      </div>
    </details>
    @endcan
    @can('help.view'){!! $L('admin.help.index','♡','Help & Support','admin.help.*') !!}@endcan
    @can('jobs.view'){!! $L('admin.jobs.index','✦','Jobs Board','admin.jobs.*') !!}@endcan
    @can('directory.view'){!! $L('admin.directory.index','☷','Samaj Directory','admin.directory.*') !!}@endcan
    @can('feed.view'){!! $L('admin.feed.index','★','Achievements & News','admin.feed.*') !!}@endcan
    @can('engagement.view'){!! $L('admin.greetings.index','✿','Birthday & Anniversary') !!}@endcan
    @can('engagement.view'){!! $L('admin.daily.index','☀','Daily message') !!}@endcan
    @can('engagement.view'){!! $L('admin.festivals.index','❁','Festival Greetings') !!}@endcan
    @can('committee.view'){!! $L('admin.committee.index','✦','Upload Committee Member Data') !!}@endcan
    @can('reports.view')
    <details {{ request()->is('admin/reports*') || request()->routeIs('admin.matrimony.*','admin.business.*') ? 'open' : '' }}>
      <summary><span class="w-5 text-center">▤</span><span class="lbl">{{ __('Reports') }}</span><span class="chev ml-auto">›</span></summary>
      <div class="sub">
        @foreach($reports as $slug=>$label)
          @php $href = match($slug){ 'parinay'=>route('admin.matrimony.index'), 'archive-parinay'=>route('admin.matrimony.archive'), default=>route('admin.reports',$slug) };
               $act = match($slug){ 'parinay'=>request()->routeIs('admin.matrimony.index'), 'archive-parinay'=>request()->routeIs('admin.matrimony.archive'), default=>request()->is('admin/reports/'.$slug) }; @endphp
          <a href="{{ $href }}" class="{{ $act ? 'active' : '' }}">{{ __($label) }}</a>
        @endforeach
      </div>
    </details>
    @endcan
    @can('notifications.view'){!! $L('admin.notifications.index','✉','Notification','admin.notifications.*') !!}@endcan
    @can('notifications.view'){!! $L('admin.meetings','◷','My Meetings/ Events') !!}@endcan
    {!! $L('admin.vandana','♪','Mahesh Vandana') !!}
    @if(auth()->user()->canany(['business.view','events.view','donations.view','import.run','users.view','masters.view','settings.manage','trusts.view','customfields.view','downloads.view','geography.view','plans.view','payments.view','forms.view','verification.view','messaging.view','revenue.view','polls.view','rsvp.view','points.view','corrections.view','community.view','directories.view','adsmgr.view','marketplace.view','ticketing.view','vendors.view','careers.view','blood.view','giving.view','invest.view','academy.view','exams.view','housie.view']))<div class="grp">{{ __('Admin tools') }}</div>@endif
    @can('polls.view'){!! $L('admin.polls','☑','Polls & Surveys','admin.polls*') !!}@endcan
    @can('rsvp.view'){!! $L('admin.rsvp','✓','Event RSVP & Attendance','admin.rsvp*') !!}@endcan
    @can('points.view'){!! $L('admin.points','★','Points & Leaderboard','admin.points') !!}@endcan
    @can('points.view'){!! $L('admin.rewards','🎁','Rewards','admin.rewards') !!}@endcan
    @can('points.view'){!! $L('admin.certificates','🎓','Certificates','admin.certificates*') !!}@endcan
    @can('corrections.view'){!! $L('admin.corrections','✎','Data corrections','admin.corrections*') !!}@endcan
    @can('community.view')<details {{ request()->is('admin/card*','admin/manage/membercats','admin/manage/benefits','admin/manage/claims','admin/manage/reports','admin/manage/msgreports') ? 'open' : '' }}><summary><span class="w-5 text-center">▣</span><span class="lbl">{{ __('Member card & networking') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.card') }}">Card tools</a><a href="{{ route('admin.manage','membercats') }}">Membership categories</a><a href="{{ route('admin.manage','benefits') }}">Vouchers & benefits</a><a href="{{ route('admin.manage','claims') }}">Redemption report</a><a href="{{ route('admin.manage','reports') }}">Member reports</a><a href="{{ route('admin.manage','msgreports') }}">Chat reports</a></div></details>@endcan
    @can('directories.view')<details {{ request()->is('admin/manage/places','admin/manage/rooms','admin/manage/bookings') ? 'open' : '' }}><summary><span class="w-5 text-center">⌂</span><span class="lbl">{{ __('Bhavan, hotel, hospital') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','places') }}">Directory</a><a href="{{ route('admin.manage','rooms') }}">Halls & rooms</a><a href="{{ route('admin.manage','bookings') }}">Booking enquiries</a></div></details>@endcan
    @canany(['marketplace.view','adsmgr.view'])<details {{ request()->is('admin/manage/categories','admin/manage/sellers','admin/manage/products','admin/manage/quotations','admin/manage/groups','admin/manage/referrals','admin/manage/adpackages','admin/manage/ads') ? 'open' : '' }}><summary><span class="w-5 text-center">₹</span><span class="lbl">{{ __('Business, ads & B2B') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      @can('marketplace.view')<a href="{{ route('admin.manage','categories') }}">Categories</a><a href="{{ route('admin.manage','sellers') }}">Businesses & orgs</a><a href="{{ route('admin.manage','products') }}">Products</a><a href="{{ route('admin.manage','quotations') }}">Quotations / leads</a><a href="{{ route('admin.manage','groups') }}">Business groups</a><a href="{{ route('admin.manage','referrals') }}">Referrals</a>@endcan
      @can('adsmgr.view')<a href="{{ route('admin.manage','adpackages') }}">Ad packages</a><a href="{{ route('admin.manage','ads') }}">Paid ads</a>@endcan</div></details>@endcanany
    @can('ticketing.view')<details {{ request()->is('admin/tickets*','admin/manage/tickettypes','admin/manage/orders','admin/manage/photos') ? 'open' : '' }}><summary><span class="w-5 text-center">🎟</span><span class="lbl">{{ __('Tickets & online events') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.tickets') }}">Events & sales</a><a href="{{ route('admin.manage','tickettypes') }}">Ticket types</a><a href="{{ route('admin.manage','orders') }}">Orders</a><a href="{{ route('admin.manage','photos') }}">Event photos</a></div></details>@endcan
    @can('vendors.view')<details {{ request()->is('admin/manage/vendors','admin/manage/vendorquotes') ? 'open' : '' }}><summary><span class="w-5 text-center">☗</span><span class="lbl">{{ __('Event vendors') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','vendors') }}">Vendors</a><a href="{{ route('admin.manage','vendorquotes') }}">Leads</a></div></details>@endcan
    <a href="{{ route('admin.smartsearch') }}" class="{{ request()->is('admin/smart-search') ? 'active' : '' }}"><span class="w-5 text-center">✦</span><span class="lbl">{{ __('Smart search (AI)') }}</span></a>
    @can('parichay.view')<details {{ request()->is('admin/manage/profilereports','admin/manage/profileflags','admin/manage/blocklist','admin/manage/matchmsgs','admin/manage/biodatauploads') ? 'open' : '' }}><summary><span class="w-5 text-center">⚑</span><span class="lbl">{{ __('Parichay safety') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','profilereports') }}">Profile reports</a><a href="{{ route('admin.manage','profileflags') }}">Duplicate / fraud flags</a><a href="{{ route('admin.manage','blocklist') }}">Blocklist</a><a href="{{ route('admin.manage','matchmsgs') }}">Match chats</a><a href="{{ route('admin.manage','biodatauploads') }}">Biodata uploads</a></div></details>@endcan
    @can('partners.view')<details {{ request()->is('admin/partners/*','admin/manage/bureaus','admin/manage/organisers','admin/manage/recruiters','admin/manage/partnerenq') ? 'open' : '' }}><summary><span class="w-5 text-center">❖</span><span class="lbl">{{ __('Partner dashboards') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.partners','bureau') }}">Marriage bureaus</a><a href="{{ route('admin.partners','organiser') }}">Event organisers</a><a href="{{ route('admin.partners','recruiter') }}">Job recruiters</a><a href="{{ route('admin.manage','partnerenq') }}">Leads</a></div></details>@endcan
    @can('careers.view')<details {{ request()->is('admin/manage/jobpacks','admin/manage/joborders','admin/manage/drives','admin/manage/driveregs','admin/manage/mentors','admin/manage/mentorships','admin/manage/mentorsessions') ? 'open' : '' }}><summary><span class="w-5 text-center">🎓</span><span class="lbl">{{ __('Careers & mentoring') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','jobpacks') }}">Job packs</a><a href="{{ route('admin.manage','joborders') }}">Pack orders</a><a href="{{ route('admin.manage','drives') }}">Placement drives</a><a href="{{ route('admin.manage','driveregs') }}">Drive registrations</a><a href="{{ route('admin.manage','mentors') }}">Mentors</a><a href="{{ route('admin.manage','mentorships') }}">Mentorships</a><a href="{{ route('admin.manage','mentorsessions') }}">Sessions</a></div></details>@endcan
    @can('blood.view')<details {{ request()->is('admin/manage/blooddonors','admin/manage/bloodrequests','admin/manage/blooddrives','admin/manage/bloodregs') ? 'open' : '' }}><summary><span class="w-5 text-center">🩸</span><span class="lbl">{{ __('Blood donors') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','blooddonors') }}">Donors</a><a href="{{ route('admin.manage','bloodrequests') }}">Requests</a><a href="{{ route('admin.manage','blooddrives') }}">Camps</a><a href="{{ route('admin.manage','bloodregs') }}">Camp attendance</a></div></details>@endcan
    @can('giving.view')<details {{ request()->is('admin/manage/entities','admin/manage/campaigns','admin/manage/campdonations','admin/manage/utilreports','admin/manage/assistance','admin/manage/volunteers') ? 'open' : '' }}><summary><span class="w-5 text-center">❤</span><span class="lbl">{{ __('Donations & relief') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','entities') }}">Entities & 80G</a><a href="{{ route('admin.manage','campaigns') }}">Campaigns</a><a href="{{ route('admin.manage','campdonations') }}">Campaign donations</a><a href="{{ route('admin.manage','utilreports') }}">Utilisation reports</a><a href="{{ route('admin.manage','assistance') }}">Relief requests</a><a href="{{ route('admin.manage','volunteers') }}">Volunteers</a></div></details>@endcan
    @can('invest.view')<details {{ request()->is('admin/manage/opportunities','admin/manage/investors','admin/manage/pitches','admin/manage/interests') ? 'open' : '' }}><summary><span class="w-5 text-center">📈</span><span class="lbl">{{ __('Investments') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','opportunities') }}">Opportunities</a><a href="{{ route('admin.manage','investors') }}">Investor profiles</a><a href="{{ route('admin.manage','pitches') }}">Pitches & showcase</a><a href="{{ route('admin.manage','interests') }}">Interests / leads</a></div></details>@endcan
    @if(auth()->user()->isSuper())<a href="{{ route('admin.system.launch') }}" class="{{ request()->routeIs('admin.system.*') ? 'active' : '' }}" title="Launch readiness"><span class="w-5 text-center">🚀</span><span class="lbl">{{ __('Launch readiness') }}</span></a>@endif
    @can('academy.view')<details {{ request()->is('admin/manage/courses','admin/manage/lessons','admin/manage/enrolments','admin/manage/earnings','admin/manage/liveclasses','admin/manage/library','admin/academy/*') ? 'open' : '' }}><summary><span class="w-5 text-center">🎓</span><span class="lbl">{{ __('Learning Academy') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.academy.ai') }}">✨ AI course draft</a><a href="{{ route('admin.manage','courses') }}">Courses</a><a href="{{ route('admin.manage','lessons') }}">Lessons / videos</a><a href="{{ route('admin.manage','enrolments') }}">Enrolments</a><a href="{{ route('admin.manage','earnings') }}">Instructor earnings</a><a href="{{ route('admin.manage','liveclasses') }}">Live classes</a><a href="{{ route('admin.manage','library') }}">Knowledge library</a></div></details>@endcan
    @can('exams.view')<details {{ request()->is('admin/manage/examq','admin/manage/exams','admin/manage/examattempts','admin/manage/learners') ? 'open' : '' }}><summary><span class="w-5 text-center">📝</span><span class="lbl">{{ __('Weekly exams') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','examq') }}">Question bank</a><a href="{{ route('admin.academy.import') }}">Import questions</a><a href="{{ route('admin.manage','exams') }}">Exams</a><a href="{{ route('admin.manage','examattempts') }}">Attempts & ranks</a><a href="{{ route('admin.manage','learners') }}">Learner segments</a></div></details>@endcan
    @can('housie.view')<details {{ request()->is('admin/manage/housiegames','admin/manage/sponsors','admin/manage/housietickets','admin/manage/housieclaims') ? 'open' : '' }}><summary><span class="w-5 text-center">🎲</span><span class="lbl">{{ __('Housie') }}</span><span class="chev ml-auto">›</span></summary><div class="sub">
      <a href="{{ route('admin.manage','housiegames') }}">Games</a><a href="{{ route('admin.manage','sponsors') }}">Sponsors & banners</a><a href="{{ route('admin.manage','housietickets') }}">Tickets</a><a href="{{ route('admin.manage','housieclaims') }}">Winners & claims</a></div></details>@endcan
    @can('business.view'){!! $L('admin.business.index','₹','Businesses') !!}@endcan
    @can('business.view'){!! $L('admin.business.reviews','★','Business Reviews','admin.business.reviews') !!}@endcan
    @can('business.view'){!! $L('admin.business.ads','◉','Advertisements') !!}@endcan
    @can('events.view'){!! $L('admin.events.index','◔','Public Events') !!}@endcan
    @can('donations.view'){!! $L('admin.donations.index','❀','Donations') !!}@endcan
    @can('donations.view'){!! $L('admin.messages','☎','Contact messages') !!}@endcan
    @can('import.run'){!! $L('admin.import','⇪','Purana data import') !!}@endcan
    @can('users.view'){!! $L('admin.team.index','☷','Team / Sub-Admins','admin.team.*') !!}@endcan
    @can('masters.view')
    <details {{ request()->is('admin/masters*') ? 'open' : '' }}>
      <summary><span class="w-5 text-center">☰</span><span class="lbl">{{ __('Master Data') }}</span><span class="chev ml-auto">›</span></summary>
      <div class="sub">
        @foreach(config('masters.groups') as $grp)
          <div class="grp" style="padding-left:.75rem">{{ $grp }}</div>
          @foreach(config('masters.types') as $key=>$mt)@if($mt['group']===$grp)
            <a href="{{ route('admin.masters.index',$key) }}" class="{{ request()->is('admin/masters/'.$key) ? 'active' : '' }}">{{ $mt['label'] }}</a>
          @endif @endforeach
        @endforeach
      </div>
    </details>
    @endcan
    @can('trusts.view'){!! $L('admin.trusts.index','✚','Trusts','admin.trusts.index') !!}{!! $L('admin.trusts.submissions','⇧','Trust Submissions') !!}@endcan
    @can('geography.view'){!! $L('admin.locations','📍','Locations (State→Pincode)') !!}{!! $L('admin.geography','🌐','Countries & Zones') !!}@endcan
    @can('plans.view'){!! $L('admin.plans','◈','Plans & Coupons') !!}{!! $L('admin.subscriptions','☑','Subscribers') !!}<a href="{{ route('admin.manage','iapproducts') }}" title="iOS products"><span class="w-5 text-center"></span><span class="lbl">iOS products</span></a><a href="{{ route('admin.manage','iaptx') }}" title="iOS purchases"><span class="w-5 text-center"></span><span class="lbl">iOS purchases</span></a>@endcan
    @can('payments.view'){!! $L('admin.payments','₹','Payments & Receipts') !!}@endcan
    @can('revenue.view'){!! $L('admin.revenue','▲','Revenue') !!}@endcan
    @can('forms.view'){!! $L('admin.forms.index','▤','Online Forms','admin.forms.index') !!}{!! $L('admin.forms.submissions','✔','Form Approvals') !!}@endcan
    @can('verification.view'){!! $L('admin.verification','✓','Member Verification') !!}@endcan
    @can('messaging.view'){!! $L('admin.messaging','✉','WhatsApp / SMS / Email') !!}@endcan
    @can('settings.manage'){!! $L('admin.translations','文','Languages') !!}@endcan
    @can('downloads.view'){!! $L('admin.downloads.index','⇩','App Downloads') !!}@endcan
    @can('customfields.view'){!! $L('admin.customfields','＋','Form Fields') !!}@endcan
    @can('trusts.edit'){!! $L('admin.departments','▦','Departments') !!}@endcan
    @can('settings.manage'){!! $L('admin.settings','⚙','Settings') !!}@endcan
    {!! $L('admin.account.password','🔑','Change password') !!}
  </aside>
  <div id="scrim" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleSide()"></div>

  <div class="min-w-0 flex flex-col min-h-screen">
    {{-- ============ HEADER ============ --}}
    <header class="topbar sticky top-0 z-30 backdrop-blur border-b flex items-center justify-between gap-2 px-3 md:px-5 h-16" style="background:color-mix(in srgb,var(--bg) 88%,transparent);border-color:var(--line)">
      <div class="flex items-center gap-2 min-w-0">
        <button class="lg:hidden btn btn-ghost btn-sm" onclick="toggleSide()" aria-label="Menu">☰</button>
        <button class="hidden lg:inline-flex btn btn-ghost btn-sm" onclick="toggleCollapse()" aria-label="Collapse menu" title="Menu collapse">⇔</button>
        <h1 class="font-display text-lg md:text-xl truncate">@yield('title','Dashboard')</h1>
      </div>
      <div class="flex items-center gap-1.5">
        <a href="{{ route('admin.families.create') }}" class="btn btn-ghost btn-sm hidden xl:inline-flex">+ {{ __('Add New Family') }}</a>
        <button class="btn btn-primary btn-sm" onclick="openM('donateM')">❀ <span class="hidden sm:inline">{{ __('Donate Now') }}</span></button>

        {{-- language --}}
        <div class="dd"><button class="btn btn-ghost btn-sm" onclick="dd(this)">🌐 <span class="hidden sm:inline">{{ app()->getLocale()==='hi' ? 'हिन्दी' : 'English' }}</span></button>
          <div class="dd-m card"><a href="{{ route('lang','en') }}">English</a><a href="{{ route('lang','hi') }}">हिन्दी</a></div></div>

        {{-- bell --}}
        <div class="dd"><button class="btn btn-ghost btn-sm relative" onclick="dd(this)" aria-label="Notifications">🔔 @if($unread)<span class="dot">{{ $unread }}</span>@endif</button>
          <div class="dd-m card !min-w-[290px]"><div class="px-3 py-2 flex justify-between items-center text-sm"><b>{{ __('Notifications') }}</b>
            <form method="post" action="{{ route('admin.bell.read') }}">@csrf<button class="underline text-xs">{{ __('Mark all as read') }}</button></form></div>
            @forelse($bell as $b)<a href="{{ $b->url ?: '#' }}" class="{{ $b->read_at ? 'opacity-60' : 'font-semibold' }}">{{ $b->title }}</a>@empty<div class="px-3 py-3 text-sm" style="color:var(--muted)">{{ __('No New Notification') }}</div>@endforelse
            <a href="{{ route('admin.notifications.index') }}" class="justify-center underline">{{ __('Read all') }}</a></div></div>

        {{-- theme / display settings --}}
        <div class="dd"><button class="btn btn-ghost btn-sm" onclick="dd(this)" aria-label="Display settings">◐</button>
          <div class="dd-m card">
            <button class="item" onclick="setTheme('light')">☀ {{ __('White Mode') }}</button><button class="item" onclick="setTheme('dark')">☾ {{ __('Dark Mode') }}</button>
            <button class="item" onclick="toggleCollapse()">⇔ {{ __('Menu Collapse') }}</button>
            <button class="item" onclick="setImg(true)">▢ {{ __('Hide Image') }}</button><button class="item" onclick="setImg(false)">▣ {{ __('Show Image') }}</button></div></div>

        {{-- user --}}
        <div class="dd"><button class="btn btn-ink btn-sm" onclick="dd(this)"><span class="avatar w-6 h-6 rounded-full grid place-items-center text-xs" style="background:var(--saffron)">{{ mb_substr($u->name,0,1) }}</span><span class="hidden md:inline max-w-[130px] truncate">{{ $u->name }}</span></button>
          <div class="dd-m card"><div class="px-3 py-2 text-xs" style="color:var(--muted)">{{ $u->email }}<br>{{ $u->mobile }} · {{ $u->role }}</div>
            <a href="{{ route('admin.profile') }}">👤 {{ __('Profile') }}</a>
            <button class="item" onclick="desktopNotify(this)">🖥 {{ __('Desktop Notification') }} <span id="dnState" class="ml-auto badge">{{ $u->desktop_notify ? 'ON' : 'OFF' }}</span></button>
            <a href="{{ route('admin.feedback') }}">💬 {{ __('Feedback') }}</a>
            <button class="item" onclick="openM('pwM')">🔑 {{ __('Change Password') }}</button>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="item">⎋ {{ __('Logout') }}</button></form></div></div>
      </div>
    </header>

    <main class="p-3 md:p-6 w-full max-w-[1500px] fade-in grow">
      @if(session('ok'))<div class="mb-4 p-3 rounded-xl badge-ok text-sm">{{ __(session('ok')) }}</div>@endif
      @if($errors->any())<div class="mb-4 p-3 rounded-xl badge-bad text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
      @yield('content')
    </main>

    {{-- ============ FOOTER ============ --}}
    <footer class="no-print border-t mt-8 px-4 py-5 text-sm" style="border-color:var(--line)">
      <div class="flex flex-wrap justify-between gap-3 items-center">
        <span>{{ date('Y') }} © {{ \App\Models\Setting::get('copyright', $org) }}</span>
        <nav class="flex flex-wrap gap-x-4 gap-y-1" style="color:var(--muted)">
          <a class="hover:underline" target="_blank" href="{{ route('page','privacy-policy') }}">{{ __('Privacy Policy') }}</a>
          <a class="hover:underline" target="_blank" href="{{ route('page','terms-and-conditions') }}">{{ __('Term & Condition') }}</a>
          <a class="hover:underline" target="_blank" href="{{ route('contact') }}">{{ __('Contact Us') }}</a>
          <a class="hover:underline" target="_blank" href="{{ route('page','refund-policy') }}">{{ __('Refund Policy') }}</a>
          <span>{{ __('Powered By') }}: <a class="hover:underline" href="https://srkwebappinnovations.com" target="_blank" rel="noopener">Srkwebapp Innovations LLP</a></span>
        </nav>
      </div>
    </footer>
  </div>
</div>
<button class="btn btn-primary fab no-print" onclick="openM('donateM')">❀ {{ __('Donate Now') }}</button>

{{-- Donate modal (same fields as the original) --}}
<div id="donateM" class="modal" onclick="if(event.target===this)closeM('donateM')"><form method="post" action="{{ route('donate.store') }}" class="card grid gap-3">@csrf
  <div class="flex justify-between items-center"><h3 class="font-display text-xl">{{ __('Donate Now') }}</h3><button type="button" onclick="closeM('donateM')">✕</button></div>
  <div><label class="label">Amount (INR) *</label><input class="input" type="number" min="10" name="amount" required></div>
  <div><label class="label">Name (In which, receipt is to be generated) *</label><input class="input" name="name" required value="{{ $u->name }}"></div>
  <div class="grid grid-cols-2 gap-2"><div><label class="label">Family Id</label><input class="input" name="family_code"></div><div><label class="label">City (Optional)</label><input class="input" name="city"></div></div>
  <div class="grid grid-cols-2 gap-2"><div><label class="label">Email (Mandatory) *</label><input class="input" type="email" name="email" required value="{{ $u->email }}"></div><div><label class="label">Mobile (Mandatory) *</label><input class="input" name="mobile" maxlength="10" required value="{{ $u->mobile }}"></div></div>
  <div><label class="label">PAN (Optional)</label><input class="input uppercase" name="pan" maxlength="10"></div>
  <label class="flex gap-2 text-sm"><input type="checkbox" name="agree" value="1" required class="accent-orange-600 mt-1"> I agree that the donation is non-refundable. <a class="underline" target="_blank" href="{{ route('page','refund-policy') }}">Read Refund Policy</a></label>
  <label class="flex gap-2 text-sm"><input type="checkbox" name="terms" value="1" required class="accent-orange-600 mt-1"> I have read all the <a class="underline" target="_blank" href="{{ route('page','terms-and-conditions') }}">Term &amp; Condition</a> and <a class="underline" target="_blank" href="{{ route('page','privacy-policy') }}">Privacy Policy</a> and agreed to that.</label>
  <button class="btn btn-primary">{{ __('Donate Now') }}</button></form></div>

{{-- Change password modal --}}
<div id="pwM" class="modal" onclick="if(event.target===this)closeM('pwM')"><form method="post" action="{{ route('admin.password') }}" class="card grid gap-3">@csrf
  <div class="flex justify-between items-center"><h3 class="font-display text-xl">{{ __('Change Password') }}</h3><button type="button" onclick="closeM('pwM')">✕</button></div>
  <div><label class="label">Current Password</label><input class="input" type="password" name="current_password" required autocomplete="current-password"></div>
  <div><label class="label">New Password (min 10)</label><input class="input" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
  <div><label class="label">Re Enter Password</label><input class="input" type="password" name="password_confirmation" required autocomplete="new-password"></div>
  <button class="btn btn-ink">{{ __('Change Password') }}</button></form></div>

<script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
function toggleSide(){document.getElementById('side').classList.toggle('-translate-x-full');document.getElementById('scrim').classList.toggle('hidden')}
function toggleCollapse(){document.body.classList.toggle('collapsed');try{localStorage.collapsed=document.body.classList.contains('collapsed')?'1':'0'}catch(e){}}
function setTheme(t){document.documentElement.classList.toggle('dark',t==='dark');try{localStorage.theme=t}catch(e){}}
function setImg(h){document.body.classList.toggle('hide-img',h);try{localStorage.hideimg=h?'1':'0'}catch(e){}}
function dd(b){const p=b.closest('.dd'),o=p.classList.contains('open');document.querySelectorAll('.dd.open').forEach(x=>x.classList.remove('open'));if(!o)p.classList.add('open')}
document.addEventListener('click',e=>{if(!e.target.closest('.dd'))document.querySelectorAll('.dd.open').forEach(x=>x.classList.remove('open'))});
function openM(id){document.getElementById(id).classList.add('open')} function closeM(id){document.getElementById(id).classList.remove('open')}
async function desktopNotify(btn){
  const st=document.getElementById('dnState'),on=st.textContent!=='ON';
  if(on){ if(!('Notification' in window)||await Notification.requestPermission()!=='granted'){alert('Allow notification permission in the browser.');return} new Notification('ABMM Sampark',{body:'Desktop notifications are on.'}) }
  st.textContent=on?'ON':'OFF'; fetch(@json(route('admin.desktop-notify')),{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded'},body:'on='+(on?1:0)});
}
@if($u->desktop_notify && $unread)
if('Notification' in window && Notification.permission==='granted') new Notification('ABMM Sampark',{body:'{{ $unread }} new notifications'});
@endif
</script>
@stack('scripts')
</body></html>
