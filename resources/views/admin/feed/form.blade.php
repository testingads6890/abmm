@extends('layouts.admin')
@section('title', $p->exists ? 'Edit: '.($p->type==='condolence' ? 'Shok Samachar' : 'News') : ($p->type==='condolence' ? 'New Condolence Notice' : 'New News'))
@section('content')
<a class="text-sm underline" href="{{ route('admin.feed.index',['status'=>'approved','type'=>$p->type]) }}">‹ Back to list</a>
<form method="post" enctype="multipart/form-data" action="{{ $p->exists ? route('admin.feed.update',$p->id) : route('admin.feed.store') }}" class="card p-5 grid gap-3 mt-3 max-w-3xl">@csrf @if($p->exists) @method('PUT') @endif
 @if(!$p->exists)<div><label class="label">Type</label><select class="input" name="type"><option value="news" @selected($p->type==='news')>News / Notice</option><option value="condolence" @selected($p->type==='condolence')>Condolence notices (tribute)</option></select></div>@endif
 @if($p->type==='condolence')<p class="text-sm p-2 rounded" style="background:#fff7e6">Post condolence notices only with correct information: write the name, family, prayer meeting date, time and place in the body.</p>@endif
 <div><label class="label">Title *</label><input class="input" name="title" required maxlength="200" value="{{ old('title',$p->title) }}"></div>
 <div><label class="label">Details</label><textarea class="input" rows="6" name="body" maxlength="3000">{{ old('body',$p->body) }}</textarea></div>
 <div class="grid sm:grid-cols-2 gap-3">
  <div><label class="label">Image (max 3 MB)</label><input class="input" type="file" name="image" accept="image/*">@if($p->image)<label class="flex gap-2 text-sm mt-1"><input type="checkbox" name="remove_image" value="1"> Remove old image</label>@endif</div>
  <div><label class="label">District (optional)</label><input class="input" name="district" value="{{ old('district',$p->district) }}" placeholder="Blank = the whole state"></div>
  @if($pradeshList->count())<div><label class="label">State</label><select class="input" name="pradesh"><option value="">All India</option>@foreach($pradeshList as $x)<option @selected(old('pradesh',$p->pradesh)===$x)>{{ $x }}</option>@endforeach</select></div>@endif
  <div><label class="label">Publish (blank = now)</label><input class="input" type="datetime-local" name="publish_at" value="{{ old('publish_at',$p->publish_at?->format('Y-m-d\TH:i')) }}"></div>
  <div><label class="label">Expire (optional)</label><input class="input" type="datetime-local" name="expires_at" value="{{ old('expires_at',$p->expires_at?->format('Y-m-d\TH:i')) }}"></div>
  <div><label class="label">Who should the push go to?</label><select class="input" name="notify_audience">@foreach(['none'=>'No push','district'=>'That district','state'=>'That state','all'=>'Sabko'] as $k=>$l)<option value="{{ $k }}" @selected(old('notify_audience',$p->notify_audience ?? 'none')===$k)>{{ $l }}</option>@endforeach</select>
   @if($p->notified_at)<div class="text-xs" style="color:var(--muted)">Push bheja ja chuka hai ({{ $p->notified_at->format('d-M H:i') }})</div>@endif</div>
 </div>
 <label class="flex gap-2 text-sm"><input type="checkbox" name="pinned" value="1" @checked(old('pinned',$p->pinned))> Pin to top</label>
 <div class="flex gap-2"><button class="btn btn-primary">{{ $p->exists ? 'Save' : 'Publish' }}</button></div>
</form>
@endsection
