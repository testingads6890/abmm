@extends('layouts.admin')
@section('title', $p ? 'Edit poll' : 'New poll / survey')
@section('content')
<form method="post" action="{{ $p ? route('admin.polls.update',$p->id) : route('admin.polls.store') }}" class="card p-4 grid gap-3 max-w-3xl">
 @csrf @if($p) @method('PUT') @endif
 @if($errors->any())<div class="text-sm" style="color:#b00">{{ $errors->first() }}</div>@endif
 <div class="grid sm:grid-cols-3 gap-2"><label class="text-sm">Type<select class="input" name="kind"><option value="poll" @selected(old('kind',$p->kind ?? '')==='poll')>Poll (1 question)</option><option value="survey" @selected(old('kind',$p->kind ?? '')==='survey')>Survey (many)</option></select></label>
  <label class="text-sm sm:col-span-2">Title<input class="input" name="title" required value="{{ old('title',$p->title ?? '') }}"></label></div>
 <label class="text-sm">Description<textarea class="input" name="description" rows="2">{{ old('description',$p->description ?? '') }}</textarea></label>
 <label class="text-sm">Questions (ek line = ek sawal)<textarea class="input font-mono" name="questions_text" rows="8" required placeholder="single | Which city do you live in? | Indore,Mumbai,Delhi | 1&#10;rating | How was the event? | | 1&#10;text | Suggestion | | 0">{{ old('questions_text',$text) }}</textarea>
  <span class="text-xs" style="color:var(--muted)">Format: type | Question | option1,option2 | required(1/0). Types: single, multi, text, rating (1-5).</span></label>
 <div class="grid sm:grid-cols-2 gap-2"><label class="text-sm">Start<input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at',$p->starts_at ?? '') }}"></label><label class="text-sm">End<input class="input" type="datetime-local" name="ends_at" value="{{ old('ends_at',$p->ends_at ?? '') }}"></label></div>
 <div><div class="text-sm mb-1">Target audience (blank = all members)</div><div class="grid sm:grid-cols-3 gap-2">
  @foreach(\App\Services\GeoTarget::KEYS as $k)<input class="input" name="{{ $k }}" placeholder="{{ ucfirst($k) }}" value="{{ old($k,$target[$k] ?? '') }}">@endforeach</div></div>
 <div class="flex gap-4 text-sm"><label><input type="checkbox" name="show_results" value="1" @checked(old('show_results',$p->show_results ?? true))> Show results after voting</label><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$p->is_active ?? true))> Active</label></div>
 <div><button class="btn btn-ink">Save</button> <a class="btn" href="{{ route('admin.polls') }}">Cancel</a></div>
</form>
@endsection
