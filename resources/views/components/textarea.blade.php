@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false, 'rows' => 4])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $hasError = $errors->has($key);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              @if ($hint) aria-describedby="{{ $id }}-hint" @endif
              aria-invalid="{{ $hasError ? 'true' : 'false' }}"
              {{ $attributes->except('id')->merge(['class' => 'input']) }}>{{ old($key, $value) }}</textarea>
    @if ($hint)<span id="{{ $id }}-hint" class="hint">{{ $hint }}</span>@endif
    @error($key)<span class="error-text">{{ $message }}</span>@enderror
</div>
