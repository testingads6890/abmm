@extends('layouts.admin')
@section('title','Job: '.$j->title)
@section('content')
<a class="text-sm underline" href="{{ route('admin.jobs.index',['status'=>$j->status]) }}">‹ Back to list</a>
<div class="grid lg:grid-cols-3 gap-4 mt-3">
 <div class="card p-5 lg:col-span-2 grid gap-2">
  <div class="text-sm" style="color:var(--muted)">{{ \App\Models\JobPost::TYPES[$j->job_type] ?? $j->job_type }} · <b>{{ \App\Models\JobPost::STATUSES[$j->status] ?? $j->status }}</b></div>
  <h2 class="font-display text-2xl">{{ $j->title }}</h2>
  <p class="whitespace-pre-line">{{ $j->description }}</p>
  <div class="text-sm grid sm:grid-cols-2 gap-1 mt-2">
   <div>Company: <b>{{ $j->company ?: '—' }}</b></div><div>Category: {{ $j->category ?: '—' }}</div><div>Work mode: {{ ['onsite'=>'On-site','remote'=>'Work from home','hybrid'=>'Hybrid'][$j->work_mode] ?? $j->work_mode }}</div><div>Education: {{ $j->education ?: '—' }}</div><div>Gender: {{ ['any'=>'Male / Female','male'=>'Male','female'=>'Female'][$j->gender_pref] ?? 'Any' }}</div><div>City: {{ $j->city ?: '—' }}</div><div>Salary: {{ $j->salary ?: '—' }}</div>
   <div>Contact: <b>{{ $j->contact_name ?: '—' }}</b> {{ $j->contact_phone }}</div><div>Pradesh: {{ $j->pradesh ?: '—' }}</div><div>District: {{ $j->district ?: '—' }}</div>
   <div>Experience: {{ $j->experience ?: '—' }}</div><div>Vacancies: {{ $j->vacancies }}</div><div>Skills: {{ $j->skills ?: '—' }}</div><div>Daali: {{ $j->created_at?->format('d-M-Y H:i') }}</div><div>Views: {{ $j->views }}</div>
   @if($j->expires_at)<div>Expiry: {{ $j->expires_at->format('d-M-Y') }}</div>@endif
  </div>
  <h3 class="font-display text-lg mt-3">Applicants ({{ $j->applications->count() }})</h3>
  <table class="tbl"><tbody>@forelse($j->applications as $a)<tr><td>{{ $a->profile?->name ?? '—' }}<div class="text-xs" style="color:var(--muted)">{{ $a->profile?->headline }}</div></td><td>{{ $a->profile?->phone }}</td><td>{{ \App\Models\JobPost::APP_STATUSES[$a->status] ?? $a->status }}</td></tr>
  @empty<tr><td style="color:var(--muted)">No applications yet.</td></tr>@endforelse</tbody></table>
  @if($j->admin_note)<div class="text-sm p-2 rounded" style="background:#fff7e6">Note: {{ $j->admin_note }}</div>@endif
 </div>
 <div class="grid gap-4 content-start">
  @can('jobs.edit')
  <form method="post" action="{{ route('admin.jobs.decide',$j->id) }}" class="card p-4 grid gap-2">@csrf<h3 class="font-display text-lg">Decision</h3>
   <select class="input" name="status"><option value="live" @selected($j->status!=='rejected' && $j->status!=='closed')>Approve (live)</option><option value="rejected" @selected($j->status==='rejected')>Reject</option><option value="closed" @selected($j->status==='closed')>Close</option></select>
   <label class="text-sm">Days live <input class="input" type="number" name="days" min="1" max="365" value="30"></label>
   <textarea class="input" rows="3" name="note" placeholder="A reason is required when rejecting (shown to the member)">{{ old('note',$j->admin_note) }}</textarea>
   <button class="btn btn-primary">Save & notify the member</button></form>
  @endcan
  @can('jobs.delete')<form method="post" action="{{ route('admin.jobs.destroy',$j->id) }}" onsubmit="return confirm('Remove permanently?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost">Delete</button></form>@endcan
 </div>
</div>
@endsection
