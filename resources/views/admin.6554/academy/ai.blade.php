@extends('layouts.admin')
@section('title','AI course draft')
@section('content')
<form method="post" action="{{ route('admin.academy.ai.store') }}" class="card p-4 grid gap-3 max-w-2xl">@csrf
 <div class="text-sm opacity-80">Enter a topic - the AI will draft lesson names, summaries, narration scripts and an MCQ question bank. {{ $aiOn ? '✦ Claude AI active.' : 'The AI key is not set, so a simple skeleton will be created that you can edit.' }} You add the video link yourself (YouTube / Vimeo / an mp4 made with an AI video tool).</div>
 <label>Topic<input class="input" name="topic" value="{{ old('topic') }}" placeholder="e.g. Basics of GST for small traders" required></label>
 <div class="grid grid-cols-2 gap-3"><label>Segment<select class="input" name="segment">@foreach($segments as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
 <label>Lessons<input class="input" type="number" name="lessons" value="5" min="2" max="12"></label></div>
 <label>Instructor member ID (optional)<input class="input" type="number" name="instructor_user_id"></label>
 @if($errors->any())<div class="text-red-600 text-sm">{{ $errors->first() }}</div>@endif
 <div><button class="btn btn-primary">Draft course</button> <a class="btn btn-ghost" href="{{ route('admin.manage','courses') }}">All courses</a></div>
</form>
@if($recent->count())<div class="card p-3 mt-4 max-w-2xl"><div class="font-semibold mb-1">Recent AI drafts</div>@foreach($recent as $c)<div><a href="{{ route('admin.manage.edit',['courses',$c->id]) }}">{{ $c->title }}</a> <span class="opacity-60 text-sm">{{ $c->status }}</span></div>@endforeach</div>@endif
@endsection
