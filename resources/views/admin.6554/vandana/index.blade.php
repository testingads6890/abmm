@extends('layouts.admin')
@section('title','Mahesh Vandana')
@section('content')
<div class="grid lg:grid-cols-2 gap-4">
<div class="card p-6 text-center"><h2 class="font-display text-2xl mb-4">Mahesh Vandana</h2>
 @if($audio)<audio id="au" src="{{ asset('storage/'.$audio) }}" preload="none"></audio>
  <div class="flex justify-center gap-3 my-4">
   <button class="btn btn-primary" onclick="au.paused?au.play():au.pause();this.textContent=au.paused?'▶ Play':'⏸ Pause'">▶ Play</button>
   <button class="btn btn-ghost" onclick="au.muted=!au.muted;this.textContent=au.muted?'🔇 Unmute':'🔊 Mute'">🔊 Mute</button>
   <a class="btn btn-ghost" href="{{ asset('storage/'.$audio) }}" download>⇩ Download</a></div>
  <input type="range" min="0" max="100" value="0" id="seek" class="w-full accent-orange-600" oninput="au.currentTime=au.duration*this.value/100"><script>au.ontimeupdate=()=>{seek.value=au.duration?au.currentTime/au.duration*100:0}</script>
 @else<p style="color:var(--muted)">Audio not uploaded yet.</p>@endif
 <p class="text-xs mt-4" style="color:var(--muted)">Play/pause, mute and download buttons. Image download: right-click → Save image as on PC; press and hold for 2 seconds on mobile.</p></div>
<div class="card p-6 text-center">@if($image)<img src="{{ asset('storage/'.$image) }}" alt="Mahesh Vandana" class="mx-auto rounded-2xl max-h-[420px]"><a class="btn btn-ghost mt-4" href="{{ asset('storage/'.$image) }}" download>⇩ Image download</a>@else<p style="color:var(--muted)">Image not uploaded yet.</p>@endif</div>
@if(auth()->user()->isAdmin())<form method="post" enctype="multipart/form-data" action="{{ route('admin.vandana.upload') }}" class="card p-5 grid gap-3 lg:col-span-2 sm:grid-cols-3">@csrf<div><label class="label">Audio (mp3/wav, 20MB)</label><input class="input" type="file" name="audio" accept="audio/*"></div><div><label class="label">Image</label><input class="input" type="file" name="image" accept="image/*"></div><button class="btn btn-ink self-end">Upload</button></form>@endif
</div>
@endsection
