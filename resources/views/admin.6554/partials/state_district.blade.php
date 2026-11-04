@php($u = 'sd'.uniqid())
{{-- Dependent State -> District selects. Params: $sdMap (state=>[districts]), $stateName, $districtName, $stateVal, $districtVal, $cls --}}
<select class="input {{ $cls ?? '' }}" name="{{ $stateName ?? 'state' }}" id="{{ $u }}s"><option value="">State</option>@foreach(array_keys($sdMap) as $st)<option value="{{ $st }}" @selected(($stateVal ?? '')===$st)>{{ $st }}</option>@endforeach</select>
<select class="input {{ $cls ?? '' }}" name="{{ $districtName ?? 'district' }}" id="{{ $u }}d"><option value="">District</option></select>
<script>(function(){var m=@json($sdMap),s=document.getElementById('{{ $u }}s'),d=document.getElementById('{{ $u }}d'),cur=@json($districtVal ?? '');
function fill(){d.innerHTML='<option value="">District</option>';(m[s.value]||[]).forEach(function(x){var o=document.createElement('option');o.value=o.textContent=x;if(x===cur)o.selected=true;d.appendChild(o);});cur='';}
s.addEventListener('change',fill);fill();})();</script>
