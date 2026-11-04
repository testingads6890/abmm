@extends('layouts.admin')
@section('title','Family '.$family->family_code)
@section('content')
<div class="grid lg:grid-cols-3 gap-4">
 <div class="card p-5 lg:col-span-1"><div class="w-20 h-20 rounded-full grid place-items-center text-white font-display text-3xl mb-3" style="background:linear-gradient(135deg,var(--ink),var(--saffron))">{{ mb_substr($family->head_name,0,1) }}</div>
  <h2 class="font-display text-2xl">{{ $family->head_name }}</h2><span class="badge">ID {{ $family->family_code }}</span> <span class="badge {{ $family->status==='active'?'badge-ok':'badge-warn' }}">{{ $family->status }}</span>
  <dl class="mt-4 text-sm grid gap-2">
   @foreach(['Father/Husband'=>$family->father_husband_name,'Mobile'=>$family->mobile,'Email'=>$family->email,'Address'=>$family->address.', '.$family->city,'District'=>$family->district.', '.$family->state,'Pincode'=>$family->pincode,'Pradesh'=>$family->pradesh,'Blood group'=>$family->blood_group,'Occupation'=>$family->occupation,'Aadhar'=>$family->aadhar_masked,'Members'=>$family->member_count] as $k=>$v)
    @if($v)<div class="flex justify-between gap-3 border-b pb-1" style="border-color:var(--line)"><dt style="color:var(--muted)">{{ $k }}</dt><dd class="text-right">{{ $v }}</dd></div>@endif @endforeach
  </dl><div class="mt-4 flex gap-2"><a class="btn btn-ink btn-sm" href="{{ route('admin.families.edit',$family) }}">Edit</a>
   <form method="post" action="{{ route('admin.families.destroy',$family) }}" onsubmit="return confirm('Mark the family inactive?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Inactive</button></form></div></div>
 <div class="lg:col-span-2 grid gap-4 content-start">
  @php $active=$family->members->where('status','active'); $arch=$family->members->where('status','archived'); @endphp
  @if(count($xd = \App\Support\CustomForm::display('family',$family->extra)))<div class="card p-5"><h3 class="font-display text-lg mb-3">Additional details</h3><dl class="grid sm:grid-cols-2 gap-2 text-sm">@foreach($xd as $xl=>$xv)<div><dt class="label">{{ $xl }}</dt><dd>{{ $xv !== '' ? $xv : '—' }}</dd></div>@endforeach</dl></div>@endif
  <div class="card p-5"><h3 class="font-display text-lg mb-3">Family members ({{ $active->count() }})</h3>
   <div class="overflow-x-auto"><table class="tbl stack"><thead><tr><th>Member ID</th><th>Name</th><th>Relation</th><th>DOB / Age</th><th>Education</th><th>Mobile</th><th>Send to Hanging Data</th></tr></thead><tbody>
   @forelse($active as $m)<tr><td data-l="ID">{{ $m->member_code }}</td><td data-l="Name">{{ $m->name }} @if($m->is_head)<span class="badge">HOF</span>@endif</td><td data-l="Relation">{{ $m->relation }}</td><td data-l="DOB">{{ $m->dob?->format('d-M-Y') }} {{ $m->age !== null ? '('.$m->age.'y)' : '' }}</td><td data-l="Education">{{ $m->education }}</td><td data-l="Mobile">{{ $m->mobile }}</td>
    <td data-l="Archive">@unless($m->is_head)<form method="post" action="{{ route('admin.families.members.archive',[$family,$m]) }}" class="flex gap-1" onsubmit="return confirm('Remove this member from the family?')">@csrf<select name="archive_type" class="input !min-h-[34px] !py-1 text-xs">@foreach(\App\Models\Member::ARCHIVE_TYPES as $t)<option>{{ $t }}</option>@endforeach</select><button class="btn btn-ghost btn-sm">Move</button></form>@endunless</td></tr>
   @empty<tr><td colspan="7" style="color:var(--muted)">No members yet.</td></tr>@endforelse</tbody></table></div></div>
  @if($arch->count())<div class="card p-5"><h3 class="font-display text-lg mb-3">Hanging Data ({{ $arch->count() }})</h3>
   @foreach($arch as $m)<div class="flex flex-wrap justify-between items-center gap-2 py-2 border-b text-sm" style="border-color:var(--line)"><span>{{ $m->name }} <span class="badge badge-warn">{{ $m->archive_type }}</span> <span style="color:var(--muted)">{{ $m->archived_at?->format('d M Y') }}</span></span>
    <form method="post" action="{{ route('admin.families.members.restore',[$family,$m]) }}">@csrf<button class="btn btn-ghost btn-sm">Restore</button></form></div>@endforeach</div>@endif
  @php $o = fn($k,$d=[]) => $lk[$k] ?? $d;
       $sel = function($n,$l,$opts,$req=false){ echo '<div><label class="label">'.$l.($req?' *':'').'</label><select class="input" name="'.$n.'"'.($req?' required':'').'><option value="">Select</option>'; foreach($opts as $x) echo '<option>'.e($x).'</option>'; echo '</select></div>'; };
       $inp = function($n,$l,$req=false,$t='text',$ex=''){ echo '<div><label class="label">'.$l.($req?' *':'').'</label><input class="input" type="'.$t.'" name="'.$n.'" '.$ex.($req?' required':'').'></div>'; }; @endphp
  <form id="add-member" method="post" enctype="multipart/form-data" action="{{ route('admin.families.members.store',$family) }}" class="card p-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">@csrf
   <h3 class="font-display text-lg sm:col-span-2 lg:col-span-3">Add New Member</h3>
   @php $inp('name','Name',true); $inp('father_husband_name',"Father's/ Husband Name"); $sel('gender','Gender',$o('gender',['MALE','FEMALE']),true); $sel('relation','Relation',$o('relation'),true);
        $sel('caste','Caste',array_keys($casteGotras)); $sel('gotra','Gotra',$o('gotra')); $sel('country_code','Mobile country code',array_values(array_unique(array_merge(['+91'], array_values($countryCodes))))); $inp('mobile','Mobile Number',false,'text','maxlength=14 inputmode=numeric'); $inp('dob','Date of Birth',false,'date'); $sel('marital_status','Marital Status',$o('marital_status')); $inp('anniversary','Marriage Anniversary',false,'date');
        $sel('blood_group','Blood Group',$o('blood_group')); $sel('education','Education',$o('education')); $inp('education_detail','Education Detail'); $sel('occupation','Occupation',$o('occupation')); $inp('occupation_detail','Occupation Detail');
        $inp('email','Email',false,'email'); $inp('aadhar','AADHAR No',false,'text','maxlength=12 inputmode=numeric'); $inp('photo','Image',false,'file','accept=image/*'); @endphp
   @include('admin.custom._inputs',['form'=>'member','values'=>[]])
   <button class="btn btn-primary sm:col-span-2 lg:col-span-3">Save Changes</button></form>
  </div></div>
@endsection
