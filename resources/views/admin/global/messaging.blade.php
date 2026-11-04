@extends('layouts.admin')
@section('title','WhatsApp / SMS / Email')
@section('content')
@if($errors->any())<div class="mb-4 p-3 rounded-xl text-sm" style="background:#fee;color:#900">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="grid sm:grid-cols-3 gap-3 mb-4"><div class="card p-4 text-sm"><b>WhatsApp API:</b> {{ $wa ? 'Configured' : 'Not configured (.env WHATSAPP_*)' }}</div>
 <div class="card p-4 text-sm"><b>WhatsApp opt-ins:</b> yes {{ $optins[1] ?? 0 }} · no {{ $optins[0] ?? 0 }}</div>
 <div class="card p-4 text-sm"><b>Last 30 days:</b> @foreach($stats as $s){{ $s->channel }}/{{ $s->status }}: {{ $s->c }}{{ $s->cost>0 ? ' (₹'.number_format($s->cost,2).')' : '' }}; @endforeach</div></div>
@can('messaging.send')
<form method="post" action="{{ route('admin.messaging.send') }}" class="card p-5 grid sm:grid-cols-3 gap-3 mb-5" id="mf">@csrf<h3 class="font-display text-lg col-span-full">Send message</h3>
 <div><label class="label">Channel</label><select class="input" name="channel"><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">E-mail</option></select></div>
 <div><label class="label">Category</label><select class="input" name="category"><option value="marketing">Marketing / greeting (opt-in only)</option><option value="utility">Utility / service</option></select></div>
 <div><label class="label">WhatsApp template name</label><input class="input" name="template" placeholder="approved template"></div>
 <div><label class="label">Level</label><select class="input" name="level">@foreach($levels as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
 <div><label class="label">Country</label><input class="input" name="country" value="{{ auth()->user()->isSuper() ? '' : auth()->user()->country }}" {{ auth()->user()->isSuper() ? '' : 'readonly' }}></div>
 <div><label class="label">State</label><input class="input" name="state"></div>
 <div><label class="label">District</label><input class="input" name="district"></div>
 <div><label class="label">City</label><input class="input" name="city" value="{{ auth()->user()->city }}" {{ auth()->user()->city && !auth()->user()->isSuper() ? 'readonly' : '' }}></div>
 <div><label class="label">Zone</label><input class="input" name="zone" list="zl" value="{{ auth()->user()->zone }}" {{ auth()->user()->zone && !auth()->user()->isSuper() ? 'readonly' : '' }}><datalist id="zl">@foreach($zones as $z)<option value="{{ $z->name }}">{{ $z->city }}@endforeach</datalist></div>
 <div class="sm:col-span-2"><label class="label">Subject (e-mail)</label><input class="input" name="subject"></div>
 <div class="col-span-full"><label class="label">Message</label><textarea class="input" name="text" rows="3" maxlength="900" required></textarea></div>
 <div class="col-span-full flex flex-wrap items-center gap-3"><button type="button" class="btn btn-ghost" onclick="est()">Estimate audience & cost</button><span id="estOut" class="text-sm" style="color:var(--muted)"></span><button class="btn btn-primary ml-auto">Queue send</button></div>
</form>
<script>function est(){var f=document.getElementById('mf'),d=new FormData(f);fetch('{{ route('admin.messaging.estimate') }}',{method:'POST',body:d,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}}).then(r=>r.json()).then(j=>{document.getElementById('estOut').textContent=j.families+' families · WhatsApp approx ₹'+j.whatsapp_cost_inr_incl_gst+' incl. GST (₹'+j.rate_per_message+'/msg)';});}</script>
@endcan
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>When</th><th>Channel</th><th>To</th><th>Category</th><th>Status</th><th>Info</th></tr></thead><tbody>
@foreach($recent as $m)<tr><td>{{ \Carbon\Carbon::parse($m->created_at)->format('d M H:i') }}</td><td>{{ $m->channel }}</td><td>{{ $m->to }}</td><td>{{ $m->category }}</td><td>{{ $m->status }}</td><td class="text-xs">{{ \Illuminate\Support\Str::limit($m->error ?: $m->template ?: $m->body, 70) }}</td></tr>@endforeach</tbody></table></div>
@endsection
