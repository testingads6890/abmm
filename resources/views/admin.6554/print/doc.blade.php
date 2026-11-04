<!doctype html><html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=Inter:wght@400;600&family=Noto+Sans+Devanagari&display=swap" rel="stylesheet">
<style>
 body{font-family:Inter,'Noto Sans Devanagari',sans-serif;color:#1d1b2e;margin:0;padding:24px;background:#fff;font-size:14px}
 h1{font-family:Fraunces,serif;margin:0;font-size:22px;color:#1b1f4b} .sub{color:#6b6a7c;font-size:12px;margin-top:2px}
 .head{border-bottom:3px solid #e8772e;padding-bottom:10px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:end;gap:12px}
 table{border-collapse:collapse;width:100%;margin:10px 0} th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;vertical-align:top} th{background:#f6efe4;width:32%;font-weight:600}
 table.list th{width:auto;background:#1b1f4b;color:#fff} .btns{margin-bottom:12px} button{padding:8px 16px;border:0;border-radius:999px;background:#e8772e;color:#fff;font-weight:600;cursor:pointer}
 .tree ul{padding-top:18px;position:relative;display:flex;justify-content:center;gap:10px;list-style:none;margin:0;padding-left:0;flex-wrap:wrap}
 .node{display:inline-block;border:2px solid #1b1f4b;border-radius:12px;padding:8px 12px;min-width:120px;text-align:center;background:#fff}
 .node small{display:block;color:#6b6a7c} .node.head{background:#e8772e;border-color:#c85e18;color:#fff} .node.head small{color:#ffe}
 .gen{text-align:center;margin:0 0 6px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#6b6a7c} .link{height:20px;width:2px;background:#1b1f4b;margin:0 auto}
 @media print{.btns{display:none} body{padding:0}}
</style></head><body @if($autoprint) onload="window.print()" @endif>
<div class="head"><div><h1>{{ \App\Models\Setting::get('org_name') }}</h1><div class="sub">{{ $title }}</div></div><div class="sub">{{ now()->format('d-M-Y h:i A') }}</div></div>
<div class="btns"><button onclick="print()">Print</button></div>

@php $row = fn($k,$v) => $v!==null && $v!=='' ? '<tr><th>'.e($k).'</th><td>'.nl2br(e($v)).'</td></tr>' : ''; @endphp

@if($kind==='member' || $kind==='head')
 @php $m=$m; $f=$f ?? $m->family; @endphp
 <table>{!! $row('Member ID',$m?->member_code) !!}{!! $row('Family ID',$f?->family_code) !!}{!! $row('Name',trim(($m->surname ?? '').' '.$m->name)) !!}{!! $row('Relation',$m->relation) !!}{!! $row('Gender',$m->gender) !!}
  {!! $row('Date of birth',$m->dob?->format('d-M-Y').($m->age!==null?' ('.$m->age.' yrs)':'')) !!}{!! $row('Blood group',$m->blood_group) !!}{!! $row('Marital status',$m->marital_status) !!}{!! $row('Mobile',$m->mobile) !!}{!! $row('Email',$m->email) !!}
  {!! $row('Education',$m->education) !!}{!! $row('Occupation',$m->occupation) !!}{!! $row('Profession',$m->profession) !!}{!! $row('Business type',$m->business_type) !!}
  @if($f){!! $row('Address',$f->address.', '.$f->city) !!}{!! $row('District / Tehsil',$f->district.' / '.$f->tehsil) !!}{!! $row('State / Country',$f->state.' / '.$f->country) !!}{!! $row('ABMM Pradesh',$f->pradesh) !!}{!! $row('Pincode',$f->pincode) !!}{!! $row('Registered on',$f->created_at->format('d-M-Y')) !!}@endif
  @if($m->status==='archived'){!! $row('Archive type',$m->archive_type.' — '.$m->archived_at?->format('d-M-Y')) !!}@endif</table>
 @if($kind==='head')<h3>Family members</h3><table class="list"><tr><th>Member ID</th><th>Name</th><th>Relation</th><th>DOB</th></tr>@foreach($f->activeMembers as $x)<tr><td>{{ $x->member_code }}</td><td>{{ $x->name }}</td><td>{{ $x->relation }}</td><td>{{ $x->dob?->format('d-M-Y') }}</td></tr>@endforeach</table>@endif

@elseif($kind==='family')
 <p><b>Family ID {{ $f->family_code }}</b> — {{ $f->surname }} {{ $f->head_name }} · {{ $f->district }}, {{ $f->state }}</p>
 <div class="tree">
  @if($tree['parents'])<div class="gen">First generation</div><ul>@foreach($tree['parents'] as $x)<li><span class="node">{{ $x->name }}<small>{{ $x->relation }}</small></span></li>@endforeach</ul><div class="link"></div>@endif
  <div class="gen">Head</div><ul><li><span class="node head">{{ $f->head_name }}<small>Mukhiya{{ $f->dob ? ' · '.$f->dob->age.'y' : '' }}</small></span></li>@foreach($tree['mid'] as $x)<li><span class="node">{{ $x->name }}<small>{{ $x->relation }}</small></span></li>@endforeach</ul>
  @if($tree['kids'])<div class="link"></div><div class="gen">Next generation</div><ul>@foreach($tree['kids'] as $x)<li><span class="node">{{ $x->name }}<small>{{ $x->relation }}{{ $x->dob ? ' · '.$x->dob->age.'y' : '' }}</small></span></li>@endforeach</ul>@endif
  @if($tree['others'])<div class="link"></div><div class="gen">Other members</div><ul>@foreach($tree['others'] as $x)<li><span class="node">{{ $x->name }}<small>{{ $x->relation }}</small></span></li>@endforeach</ul>@endif
 </div>

@elseif($kind==='parinay')
 <table>{!! $row('Parinay ID',$p->parinay_id) !!}{!! $row('Family ID',$p->family?->family_code) !!}{!! $row('Member ID',$p->member_code) !!}{!! $row('Name',$p->name) !!}{!! $row('Gender',$p->gender) !!}{!! $row('Date of birth',$p->dob->format('d-M-Y').' ('.$p->age.' yrs)') !!}
  {!! $row('Qualification',$p->qualification) !!}{!! $row('Degree',$p->degree) !!}{!! $row('Occupation',trim($p->occupation_type.' '.$p->occupation)) !!}{!! $row('Height',$p->height) !!}{!! $row('Mobile',$p->mobile) !!}{!! $row('Address',$p->address) !!}
  {!! $row('State / District',$p->state.' / '.$p->district) !!}{!! $row('Pincode',$p->pincode) !!}{!! $row('About',$p->about) !!}{!! $row('Status',ucfirst($p->status)) !!}{!! $row('Payment ID',$p->payment_ref) !!}{!! $row('Registered',$p->registered_at?->format('d-M-Y')) !!}</table>

@elseif($kind==='business')
 <table>{!! $row('Business',$b->name) !!}{!! $row('Type',$b->type) !!}{!! $row('Member ID',$b->member_code) !!}{!! $row('Family ID',$b->family?->family_code) !!}{!! $row('Contact person',$b->contact_person) !!}{!! $row('Mobile',$b->mobile) !!}{!! $row('Email',$b->email) !!}{!! $row('Address',$b->address) !!}{!! $row('About',$b->description) !!}{!! $row('Status',ucfirst($b->status)) !!}</table>
 @if($b->reviews->count())<h3>Reviews</h3><table class="list"><tr><th>Name</th><th>Rating</th><th>Comment</th></tr>@foreach($b->reviews as $x)<tr><td>{{ $x->reviewer_name }}</td><td>{{ str_repeat('★',$x->rating) }}</td><td>{{ $x->comment }}</td></tr>@endforeach</table>@endif

@elseif($kind==='ad')
 <table>{!! $row('Business',$a->business?->name) !!}{!! $row('Title',$a->title) !!}{!! $row('Location',$a->location) !!}{!! $row('Contact person',$a->contact_person) !!}{!! $row('Mobile',$a->contact_mobile) !!}{!! $row('Period',$a->starts_on->format('d-M-Y').' – '.$a->ends_on->format('d-M-Y')) !!}{!! $row('Amount','₹'.number_format($a->amount)) !!}{!! $row('Payment ID',$a->payment_ref) !!}{!! $row('Status',ucfirst($a->status)) !!}</table>

@elseif($kind==='message')
 <table>{!! $row('Sent by',$n->sender?->name) !!}{!! $row('Sent to',ucfirst($n->audience).' '.$n->audience_value) !!}{!! $row('Type',$n->kind==='meeting'?'Meeting':'Notice') !!}{!! $row('Title',$n->title) !!}{!! $row('Message',$n->message) !!}{!! $row('Member count',$n->member_count) !!}{!! $row('Seen',$n->seen_count) !!}{!! $row('Date',$n->created_at->format('d-M-Y h:i A')) !!}</table>

@elseif($kind==='message-status')
 <p><b>{{ $n->title }}</b> — Seen {{ $n->seen_count }} / {{ $n->member_count }}</p>
 <table class="list"><tr><th>Family ID</th><th>Name</th><th>Mobile</th><th>Seen</th></tr>@foreach($rows as $x)<tr><td>{{ $x->family_code }}</td><td>{{ $x->surname }} {{ $x->head_name }}</td><td>{{ $x->mobile }}</td><td>{{ $x->seen_at ? \Illuminate\Support\Carbon::parse($x->seen_at)->format('d-M-Y h:i A') : 'Not seen' }}</td></tr>@endforeach</table>
@endif
</body></html>
