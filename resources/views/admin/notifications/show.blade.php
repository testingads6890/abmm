@extends('layouts.admin')
@section('title','Notification detail')
@section('content')
<div class="card p-5 mb-4"><h2 class="font-display text-2xl">{{ $n->title }}</h2><p class="mt-2 whitespace-pre-line">{{ $n->message }}</p>
 <p class="text-sm mt-3" style="color:var(--muted)">{{ $n->sender?->name }} · {{ ucfirst($n->audience) }} {{ $n->audience_value }} · {{ $n->created_at?->format('d M Y h:i A') }} · topic <code>{{ $n->topic }}</code></p>
 <div class="mt-3 flex gap-3"><span class="badge badge-ok">Seen {{ $seen }}</span><span class="badge badge-warn">Not seen {{ $total-$seen }}</span><span class="badge">Total {{ $total }}</span></div></div>
<div class="card p-2 overflow-x-auto"><table class="tbl stack"><thead><tr><th>Family ID</th><th>Head</th><th>Mobile</th><th>District</th><th>Seen</th></tr></thead><tbody>
@foreach($recipients as $f)<tr><td data-l="ID">{{ $f->family_code }}</td><td data-l="Head">{{ $f->surname }} {{ $f->head_name }}</td><td data-l="Mobile">{{ $f->mobile }}</td><td data-l="District">{{ $f->district }}</td><td data-l="Seen">{{ $f->seen_at ? \Illuminate\Support\Carbon::parse($f->seen_at)->format('d M h:i A') : '—' }}</td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $recipients->links() }}</div>
@endsection
