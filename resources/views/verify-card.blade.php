<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ABMM Member verification</title>
<style>body{font-family:system-ui,sans-serif;background:#faf7f2;margin:0;padding:24px;color:#222}.card{max-width:380px;margin:auto;background:#fff;border-radius:16px;box-shadow:0 4px 20px #0001;padding:22px;text-align:center}
.ok{color:#0a7a2f}.bad{color:#b00}.pill{display:inline-block;padding:3px 12px;border-radius:99px;color:#fff;font-size:13px}img{width:96px;height:96px;border-radius:50%;object-fit:cover}</style></head><body>
<div class="card">@if(!$c)<h2 class="bad">✘ Invalid card</h2><p>This QR is not a valid ABMM member card.</p>
@else<div style="display:flex;align-items:center;gap:10px;margin:0 0 8px"><img src="{{ asset('images/abmm-logo.jpg') }}" alt="ABMM logo" style="width:56px;height:56px;border-radius:50%;object-fit:cover;margin-right:10px;vertical-align:middle"><h3 style="margin:0">Akhil Bharatvarshiya Maheshwari Mahasabha</h3></div>
@if($c['photo_url'])<img src="{{ $c['photo_url'] }}" alt="">@endif
<h2 style="margin:8px 0 2px">{{ $c['name'] }}</h2><div>{{ $c['membership_no'] ?: '—' }}</div>
<p><span class="pill" style="background:{{ $c['color'] }}">{{ $c['category'] }}</span></p>
@if($c['status']==='active' || $c['status']==='expiring')<h2 class="ok">✔ Valid member</h2>@elseif($c['status']==='expired')<h2 class="bad">✘ Membership expired</h2>@else<h2 class="bad">✘ Not verified</h2>@endif
<div>{{ $c['city'] }}, {{ $c['country'] }}</div>@if($c['valid_till'])<div>Valid till {{ \Carbon\Carbon::parse($c['valid_till'])->format('d M Y') }}</div>@endif @endif</div></body></html>
