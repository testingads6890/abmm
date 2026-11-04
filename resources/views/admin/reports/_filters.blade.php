<form class="card p-4 mb-3 no-print" id="rf">
  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
  @foreach($filters as $f)
    @php [$key,$label,$kind] = $f; @endphp
    @if($kind==='dates')
      <div><label class="label">From Date</label><input class="input" type="date" name="from" value="{{ request('from') }}"></div>
      <div><label class="label">To Date</label><input class="input" type="date" name="to" value="{{ request('to') }}"></div>
    @elseif($kind==='text')
      <div class="{{ $key==='q' ? 'sm:col-span-2' : '' }}"><label class="label">{{ $label }}</label><input class="input" name="{{ $key }}" value="{{ request($key) }}"></div>
    @else
      @php $list = $kind==='lookup' ? array_combine($opts[$key==='income'?'income':$key] ?? [], $opts[$key==='income'?'income':$key] ?? []) : $f[3]; @endphp
      <div><label class="label">{{ $label }}</label><select class="input" name="{{ $key }}"><option value="">Select</option>@foreach($list as $v=>$t)<option value="{{ $v }}" @selected((string)request($key)===(string)$v)>{{ $t }}</option>@endforeach</select></div>
    @endif
  @endforeach
  </div>
  <div class="flex flex-wrap gap-2 mt-4 items-center">
    <button class="btn btn-ink">Search</button><a class="btn btn-ghost" href="{{ url()->current() }}">Reset</a>
    <span class="grow"></span>
    <button type="button" class="btn btn-ghost" onclick="print()">🖨 Print</button>
    <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['export'=>'pdf']) }}">PDF</a>
    <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['export'=>'xlsx']) }}" title="Download Excel For All At Once (Max. 1 Lakh)">Excel (.xlsx) - all at once</a>
    <a class="btn btn-ghost btn-sm" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}">CSV</a>
  </div>
</form>
