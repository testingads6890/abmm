@extends('layouts.admin')
@section('title','Add meeting')
@section('content')
@php
  $byDistrict = collect($areas)->groupBy(fn ($a) => $a['district'] ?: 'Other');
@endphp
<form method="post" action="{{ route('admin.meeting_list.store') }}" enctype="multipart/form-data" class="card p-5 grid gap-3 max-w-2xl">
  @csrf
  <h3 class="font-display text-lg">Add meeting</h3>

  <label class="grid gap-1"><span class="text-sm">Name of committee *</span>
    <select class="input" name="committee" required>
      <option value="">-- Select --</option>
      @foreach($committees as $k => $v)<option value="{{ $k }}" @selected(old('committee')===$k)>{{ $v }}</option>@endforeach
    </select>
  </label>

  <label class="grid gap-1"><span class="text-sm">Meeting type *</span>
    <select class="input" name="meeting_type" required>
      <option value="">-- Select --</option>
      @foreach($types as $k => $v)<option value="{{ $k }}" @selected(old('meeting_type')===$k)>{{ $v }}</option>@endforeach
    </select>
  </label>

  <label class="grid gap-1"><span class="text-sm">ABMM Pradesh (district-wise) *</span>
    <select class="input" name="pradesh" id="pradesh" required>
      <option value="">-- Select pradesh --</option>
      @foreach($byDistrict as $district => $items)
        <optgroup label="{{ $district }}">
          @foreach($items as $a)<option value="{{ $a['pradesh'] }}" data-anchal="{{ $a['anchal'] }}" data-district="{{ $a['district'] }}" @selected(old('pradesh')===$a['pradesh'])>{{ $a['pradesh'] }}</option>@endforeach
        </optgroup>
      @endforeach
    </select>
  </label>

  <div class="grid grid-cols-2 gap-3">
    <label class="grid gap-1"><span class="text-sm">District (auto)</span><input class="input" id="district" value="" readonly></label>
    <label class="grid gap-1"><span class="text-sm">Anchal (auto)</span><input class="input" id="anchal" value="" readonly></label>
  </div>

  <div class="grid grid-cols-2 gap-3">
    <label class="grid gap-1"><span class="text-sm">Venue *</span><input class="input" name="venue" value="{{ old('venue') }}" required maxlength="200"></label>
    <label class="grid gap-1"><span class="text-sm">Location *</span><input class="input" name="location" value="{{ old('location') }}" required maxlength="300"></label>
  </div>

  <div class="grid grid-cols-2 gap-3">
    <label class="grid gap-1"><span class="text-sm">Meeting date *</span><input class="input" type="date" name="meeting_date" value="{{ old('meeting_date') }}" required></label>
    <label class="grid gap-1"><span class="text-sm">Meeting time</span><input class="input" type="time" name="meeting_time" value="{{ old('meeting_time') }}"></label>
  </div>

  <label class="grid gap-1"><span class="text-sm">Agenda / notes</span><textarea class="input" name="agenda" rows="3" maxlength="2000">{{ old('agenda') }}</textarea></label>

  <label class="grid gap-1"><span class="text-sm">Upload (PDF or image, max 5 MB)</span><input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>

  @if($errors->any())<div class="text-sm" style="color:#b00">{{ $errors->first() }}</div>@endif

  <div class="flex gap-2"><button class="btn btn-ink btn-sm">Save meeting</button><a class="btn btn-ghost btn-sm" href="{{ route('admin.meeting_list.index') }}">Cancel</a></div>
</form>
<script>
  (function () {
    var sel = document.getElementById('pradesh');
    function sync() {
      var o = sel.options[sel.selectedIndex];
      document.getElementById('anchal').value = o && o.dataset.anchal ? o.dataset.anchal : '';
      document.getElementById('district').value = o && o.dataset.district ? o.dataset.district : '';
    }
    sel.addEventListener('change', sync);
    sync();
  })();
</script>
@endsection
