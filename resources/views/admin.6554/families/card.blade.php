<!doctype html><html><head><meta charset="utf-8"><title>Family {{ $family->family_code }}</title>
<link rel="stylesheet" href="{{ asset('css/abmm.css') }}"><style>body{background:#fff;color:#111;padding:24px;font-family:Inter,sans-serif}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px 8px;text-align:left;font-size:13px}</style></head>
<body onload="print()"><h2 style="font-family:Fraunces,serif">{{ \App\Models\Setting::get('org_name') }}</h2><h3>Family ID {{ $family->family_code }} — {{ $family->surname }} {{ $family->head_name }}</h3>
<p>{{ $family->address }}, {{ $family->city }}, {{ $family->district }}, {{ $family->state }} - {{ $family->pincode }} · {{ $family->mobile }}</p>
<table><tr><th>Member ID</th><th>Name</th><th>Relation</th><th>DOB</th><th>Blood</th><th>Education</th><th>Occupation</th></tr>
@foreach($family->activeMembers as $m)<tr><td>{{ $m->member_code }}</td><td>{{ $m->name }}</td><td>{{ $m->relation }}</td><td>{{ $m->dob?->format('d-M-Y') }}</td><td>{{ $m->blood_group }}</td><td>{{ $m->education }}</td><td>{{ $m->occupation }}</td></tr>@endforeach</table></body></html>
