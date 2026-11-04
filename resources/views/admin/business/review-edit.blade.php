@extends('layouts.admin')
@section('title','Edit review')
@section('content')
<form method="post" action="{{ route('admin.business.reviews.update',$review) }}" class="card p-5 grid gap-3 max-w-2xl">@csrf @method('PUT')
 <h3 class="font-display text-lg">Edit review: {{ $review->business?->name }}</h3>
 @if($errors->any())<div class="text-sm" style="color:var(--bad)">{{ $errors->first() }}</div>@endif
 <div><label class="label">Reviewer name</label><input class="input" name="reviewer_name" value="{{ old('reviewer_name',$review->reviewer_name) }}" required></div>
 <div><label class="label">Reviewer mobile</label><input class="input" name="reviewer_mobile" value="{{ old('reviewer_mobile',$review->reviewer_mobile) }}" maxlength="15"></div>
 <div><label class="label">Rating (1-5)</label><input class="input" type="number" min="1" max="5" name="rating" value="{{ old('rating',$review->rating) }}" required></div>
 <div><label class="label">Comment</label><textarea class="input" name="comment" rows="4">{{ old('comment',$review->comment) }}</textarea></div>
 <div><label class="label">Status</label><select class="input" name="status">@foreach(['pending','approved','rejected'] as $s)<option value="{{ $s }}" @selected(old('status',$review->status)===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
 <div class="flex gap-2"><button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="{{ url()->previous() }}">Cancel</a></div>
</form>
@endsection
