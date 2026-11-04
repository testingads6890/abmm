@extends('layouts.admin')
@section('title','Scanner: '.$e->title)
@section('content')
<div class="card p-4 max-w-xl">
 <video id="v" class="w-full rounded mb-3" playsinline style="max-height:300px;background:#000"></video>
 <div class="flex gap-2 mb-3"><input id="code" class="input" placeholder="{{ $pattern ?? 'ABMM-EV'.$e->id.'-XXXXXX' }} (manual entry)"><button id="go" class="btn btn-ink">Check in</button></div>
 <div id="out" class="text-lg font-display"></div>
</div>
<script>
const url=@json($scanUrl ?? route('admin.rsvp.scan',$e)), tok=@json(csrf_token()), out=document.getElementById('out'); let busy=false;
async function send(code){ if(busy||!code) return; busy=true;
 try{ const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tok},body:JSON.stringify({code})}); const j=await r.json();
  out.style.color=r.ok?'#0a7a2f':'#b00'; out.textContent=r.ok?('✔ '+(j.name||'')+' — '+(1+(j.guests||0))+' person(s)'):(j.result==='duplicate'?'⚠ Already checked in: '+(j.name||''):'✘ '+(j.message||j.result||'Invalid pass'));
 }catch(e){ out.textContent='Network error'; } setTimeout(()=>busy=false,1500); }
document.getElementById('go').onclick=()=>send(document.getElementById('code').value.trim());
if('BarcodeDetector' in window && navigator.mediaDevices){ const det=new BarcodeDetector({formats:['qr_code']}), v=document.getElementById('v');
 navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(s=>{v.srcObject=s;v.play();setInterval(async()=>{try{const c=await det.detect(v);if(c.length)send(c[0].rawValue);}catch(e){}},500);}).catch(()=>{v.style.display='none'});
} else document.getElementById('v').style.display='none';
</script>
@endsection
