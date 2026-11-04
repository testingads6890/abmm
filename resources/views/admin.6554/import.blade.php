@extends('layouts.admin')
@section('title','Purana data import')
@section('content')
<div class="grid lg:grid-cols-3 gap-4">
<form method="post" enctype="multipart/form-data" action="{{ route('admin.import.store') }}" class="card p-5 grid gap-3 content-start lg:col-span-1">@csrf
 <label class="label">What to import</label><select class="input" name="type"><option value="families">Families (family head)</option><option value="members">Family members</option></select>
 <input class="input" type="file" name="file" accept=".csv,.txt" required>
 <label class="flex gap-2 text-sm"><input type="checkbox" name="dry_run" value="1" checked class="accent-orange-600"> First a dry-run (nothing will be saved)</label>
 <button class="btn btn-primary">Import</button></form>
<div class="card p-5 lg:col-span-2 text-sm grid gap-2">
 <h3 class="font-display text-lg">How to</h3>
 <p>1. Data from the old system / Excel <b>CSV (UTF-8)</b> in. The first row should be the header.</p>
 <p>2. First <b>dry-run</b> run it, check the errors, then untick and do the real import.</p>
 <p>3. Running again will not create duplicates: rows are matched by Family ID → legacy ID → (mobile + name) and updated.</p>
 <p><b>Families headers (any of these names will work):</b> Family ID, Surname, Name, Father's/Husband Name, Mobile, Gender, No. of Members, Address, City, State, District, Tehsil, Pincode, ABMM Pradesh, Email, DOB, Blood Group, Education, Occupation, Business Type, Profession, Income.</p>
 <p><b>Members headers:</b> Family ID, Name, Relation, Gender, DOB, Blood Group, Education, Occupation, Mobile.</p>
 <p>For large files (20k+ rows), use the terminal: <code>php artisan abmm:import families file.csv --dry-run</code></p>
 @if(session('import_errors'))<div class="mt-2 p-3 rounded-xl badge-warn max-h-64 overflow-auto"><b>Skipped rows:</b><ul class="list-disc pl-5">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
</div></div>
@endsection
