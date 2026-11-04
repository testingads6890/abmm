@extends('layouts.admin')
@section('title','Member card tools')
@section('content')
<div class="grid lg:grid-cols-3 gap-4">
 <div class="card p-4"><h3 class="font-display text-lg mb-2">Verify member card</h3>
  <input id="tok" class="input mb-2" placeholder="ABMM-C-123-abcdef1234"><button id="chk" class="btn btn-ink btn-sm">Check</button><pre id="res" class="text-sm mt-2 whitespace-pre-wrap"></pre></div>
 @can('community.edit')<form method="post" action="{{ route('admin.card.assign') }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Assign membership category</h3>
  <input class="input" name="family_code" placeholder="Family ID" required><select class="input" name="member_category">@foreach($cats as $c)<option value="{{ $c->code }}">{{ $c->name }}</option>@endforeach</select><button class="btn btn-ink btn-sm">Save</button></form>@endcan
 @can('community.redeem')<form method="post" action="{{ route('admin.card.redeem') }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Redeem voucher code</h3>
  <input class="input" name="code" placeholder="Voucher code" required><button class="btn btn-ink btn-sm">Redeem</button></form>@endcan
</div>
<script>document.getElementById('chk').onclick=async()=>{const r=await fetch(@json(route('admin.card.lookup')),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({token:document.getElementById('tok').value.trim()})});const j=await r.json();document.getElementById('res').textContent=r.ok?(j.name+'\n'+j.membership_no+'\n'+j.category+'\nStatus: '+j.status+(j.valid_till?' (till '+j.valid_till+')':'')):j.message;};</script>
@endsection
