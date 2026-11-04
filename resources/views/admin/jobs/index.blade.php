@extends('layouts.admin')
@section('title','Jobs Board')
@section('content')
<p class="text-sm mb-3" style="color:var(--muted)">Job openings posted by employers / recruiters. They appear in the app (across India) only after you approve them. You see only the posts of employers in your area.</p>
<div class="flex flex-wrap gap-2 mb-3">
 <a class="btn btn-sm btn-ink" href="{{ route('admin.jobs.candidates') }}">Job Seekers</a>
 @foreach(\App\Models\JobPost::STATUSES as $k=>$l)<a class="btn btn-sm {{ $status===$k ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.jobs.index',['status'=>$k]) }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
</div>
<form method="get" class="card p-3 grid sm:grid-cols-2 lg:grid-cols-6 gap-2 mb-3"><input type="hidden" name="status" value="{{ $status }}">
 <input class="input lg:col-span-2" name="q" value="{{ request('q') }}" placeholder="Title / company">
 <select class="input" name="type"><option value="">Job type</option>@foreach(\App\Models\JobPost::TYPES as $k=>$l)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $l }}</option>@endforeach</select>
 <select class="input" name="category"><option value="">Category</option>@foreach($lk['job_category'] ?? [] as $c)<option @selected(request('category')===$c)>{{ $c }}</option>@endforeach</select>
 <select class="input" name="work_mode"><option value="">Work mode</option>@foreach(['onsite'=>'On-site','remote'=>'Work from home','hybrid'=>'Hybrid'] as $k=>$l)<option value="{{ $k }}" @selected(request('work_mode')===$k)>{{ $l }}</option>@endforeach</select>
 <select class="input" name="band"><option value="">Salary range</option>@foreach($bands as $b)<option @selected(request('band')===$b['label'])>{{ $b['label'] }}</option>@endforeach</select>
 <select class="input" name="education"><option value="">Education</option>@foreach($lk['education'] ?? [] as $e)<option @selected(request('education')===$e)>{{ $e }}</option>@endforeach</select>
 <select class="input" name="gender_pref"><option value="">Gender</option><option value="male" @selected(request('gender_pref')==='male')>Male</option><option value="female" @selected(request('gender_pref')==='female')>Female</option></select>
 <input class="input" type="number" min="0" max="60" name="exp" value="{{ request('exp') }}" placeholder="Experience (yrs)">
 @include('admin.partials.state_district',['sdMap'=>$sd,'stateVal'=>request('state'),'districtVal'=>request('district')])
 <button class="btn btn-ink">Filter</button><a class="btn btn-ghost" href="{{ route('admin.jobs.index',['status'=>$status]) }}">Reset</a>
</form>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Date</th><th>Title</th><th>Company</th><th>Category</th><th>Type / Mode</th><th>Salary</th><th>Exp</th><th>City, District</th><th>Applicants</th><th>Views</th><th></th></tr></thead><tbody>
@forelse($rows as $j)<tr><td class="whitespace-nowrap">{{ $j->created_at?->format('d-M-Y') }}</td><td>{{ $j->title }}</td><td>{{ $j->company ?: '—' }}</td><td>{{ $j->category ?: '—' }}</td>
 <td>{{ \App\Models\JobPost::TYPES[$j->job_type] ?? $j->job_type }}<div class="text-xs" style="color:var(--muted)">{{ ['onsite'=>'On-site','remote'=>'Work from home','hybrid'=>'Hybrid'][$j->work_mode] ?? $j->work_mode }}</div></td>
 <td>{{ $j->salary ?: '—' }}</td><td>{{ $j->experience ?: '—' }}</td><td>{{ $j->city ?: '—' }}<div class="text-xs" style="color:var(--muted)">{{ $j->district }}</div></td><td>{{ $j->applications_count }}</td><td>{{ $j->views }}</td>
 <td><a class="btn btn-sm btn-ink" href="{{ route('admin.jobs.show',$j->id) }}">Open</a></td></tr>
@empty<tr><td colspan="11" class="text-center" style="color:var(--muted)">Nothing found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $rows->links() }}</div>
@endsection
