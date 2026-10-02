@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $hasError = $errors->has($key);
    $current = $type === 'password' ? null : old($key, $value);
@endphp
<div @class([$attributes->get('wrapper-class')])>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
           @required($required)
           @if ($hint) aria-describedby="{{ $id }}-hint" @endif
           aria-invalid="{{ $hasError ? 'true' : 'false' }}"
           {{ $attributes->except(['id', 'wrapper-class'])->merge(['class' => 'input']) }}>
    @if ($hint)<span id="{{ $id }}-hint" class="hint">{{ $hint }}</span>@endif
    @error($key)<span class="error-text">{{ $message }}</span>@enderror
</div>
