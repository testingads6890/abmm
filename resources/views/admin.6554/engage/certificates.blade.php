@extends('layouts.admin')
@section('title','Certificates')
@section('content')
<div class="card p-3 overflow-x-auto"><table class="tbl"><thead><tr><th>Number</th><th>Member</th><th>Type</th><th>Title</th><th>Date</th><th></th></tr></thead><tbody>
@forelse($rows as $c)<tr><td>{{ $c->number }}</td><td>{{ $c->name }}</td><td>{{ $c->type }}</td><td>{{ $c->title }}</td><td>{{ $c->created_at }}</td><td><a class="btn btn-sm" target="_blank" href="{{ route('admin.certificates.file',$c->id) }}">PDF</a></td></tr>
@empty<tr><td colspan="6" style="color:var(--muted)">No certificates yet.</td></tr>@endforelse</tbody></table></div>
@endsection
