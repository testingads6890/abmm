{{-- Renders the Super-Admin-defined extra fields of a form. Params: $form, $values (array, optional). Inputs carry data-x="key" so modals can fill them from JS. --}}
@foreach(\App\Support\CustomForm::fields($form) as $f)
 @php $v = old('extra.'.$f['key'], ($values ?? [])[$f['key']] ?? ''); $n = 'extra['.$f['key'].']'; @endphp
 <div class="{{ $f['type']==='textarea' ? 'sm:col-span-2' : '' }}" @if($f['category']) data-cat="{{ $f['category'] }}" @endif>
  @if($f['type']==='checkbox')
   <label class="flex items-center gap-2 text-sm pt-6"><input type="hidden" name="{{ $n }}" value="0"><input type="checkbox" data-x="{{ $f['key'] }}" name="{{ $n }}" value="1" @checked($v) class="accent-orange-600"> {{ $f['label'] }}</label>
  @else
   <label class="label">{{ $f['label'] }}@if($f['required']) *@endif</label>
   @if($f['type']==='select')<select class="input" data-x="{{ $f['key'] }}" name="{{ $n }}" @required($f['required'])><option value="">-- Select --</option>@foreach($f['options'] as $o)<option @selected((string)$v===$o)>{{ $o }}</option>@endforeach</select>
   @elseif($f['type']==='textarea')<textarea class="input" rows="2" data-x="{{ $f['key'] }}" name="{{ $n }}" maxlength="2000" @required($f['required'])>{{ $v }}</textarea>
   @else<input class="input" data-x="{{ $f['key'] }}" name="{{ $n }}" value="{{ $v }}" @required($f['required']) type="{{ ['number'=>'number','date'=>'date','email'=>'email'][$f['type']] ?? 'text' }}" @if($f['type']==='number') step="any" @endif @if($f['type']==='phone') maxlength="10" inputmode="numeric" @endif>
   @endif
  @endif
 </div>
@endforeach
