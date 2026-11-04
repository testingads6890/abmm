@extends('layouts.admin')
@section('title','Job Seekers (Candidates)')
@section('content')
<a class="text-sm underline" href="{{ route('admin.jobs.index') }}">‹ Jobs Board</a>
<form class="card p-3 grid sm:grid-cols-2 lg:grid-cols-6 gap-2 my-3" method="get">
 <input class="input lg:col-span-2" name="q" value="{{ request('q') }}" placeholder="Name / skill / city">
 <select class="input" name="category"><option value="">Category</option>@foreach($lk['job_category'] ?? [] as $c)<option @selected(request('category')===$c)>{{ $c }}</option>@endforeach</select>
 <select class="input" name="education"><option value="">Education</option>@foreach($lk['education'] ?? [] as $x)<option @selected(request('education')===$x)>{{ $x }}</option>@endforeach</select>
 <select class="input" name="gender"><option value="">Gender</option><option @selected(request('gender')==='Male')>Male</option><option @selected(request('gender')==='Female')>Female</option></select>
 <select class="input" name="work_mode"><option value="">Work mode</option>@foreach(['onsite'=>'On-site','remote'=>'Work from home','hybrid'=>'Hybrid'] as $k=>$l)<option value="{{ $k }}" @selected(request('work_mode')===$k)>{{ $l }}</option>@endforeach</select>
 <select class="input" name="notice_period"><option value="">Notice period</option>@foreach(\App\Http\Controllers\Api\Member\JobController::NOTICE as $n)<option @selected(request('notice_period')===$n)>{{ $n }}</option>@endforeach</select>
 <input class="input" type="number" min="0" max="60" name="exp_min" value="{{ request('exp_min') }}" placeholder="Exp from (yrs)">
 <input class="input" type="number" min="0" max="60" name="exp_max" value="{{ request('exp_max') }}" placeholder="Exp to (yrs)">
 @include('admin.partials.state_district',['sdMap'=>$sd,'stateName'=>'_state','stateVal'=>request('_state'),'districtVal'=>request('district')])
 <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="open" value="1" @checked(request('open')==='1')> Only open to work</label>
 <button class="btn btn-ink">Filter</button><a class="btn btn-ghost" href="{{ route('admin.jobs.candidates') }}">Reset</a></form>
<div class="card p-2 overflow-x-auto"><table class="tbl"><thead><tr><th>Name</th><th>Headline</th><th>Category</th><th>Education</th><th>Exp</th><th>Expected</th><th>City</th><th>Notice</th><th>Phone</th><th>Open</th><th></th></tr></thead><tbody>
@forelse($rows as $p)<tr><td>{{ $p->name }}<div class="text-xs" style="color:var(--muted)">{{ $p->gender }}</div></td><td>{{ $p->headline }}<div class="text-xs" style="color:var(--muted)">{{ $p->skills }}</div></td><td>{{ $p->category ?: '—' }}</td><td>{{ $p->education ?: '—' }}</td><td>{{ $p->experience_years }} yr</td><td>{{ $p->expected_salary ?: '—' }}</td>
 <td>{{ $p->city ?: '—' }}<div class="text-xs" style="color:var(--muted)">{{ $p->district }}</div></td><td>{{ $p->notice_period ?: '—' }}</td><td>{{ $p->phone }}</td><td>{{ $p->open_to_work ? 'Yes' : 'No' }}</td>
 <td>@can('jobs.delete')<form method="post" action="{{ route('admin.jobs.candidates.destroy',$p->id) }}" onsubmit="return confirm('Remove the profile?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Delete</button></form>@endcan</td></tr>
@empty<tr><td colspan="11" class="text-center" style="color:var(--muted)">Nothing found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $rows->links() }}</div>
@endsection
