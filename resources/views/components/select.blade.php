@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $selected = (string) old($key, $value);
    $hasError = $errors->has($key);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @required($required)
            @if ($hint) aria-describedby="{{ $id }}-hint" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            {{ $attributes->except('id')->merge(['class' => 'input']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $optionValue === $selected)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)<span id="{{ $id }}-hint" class="hint">{{ $hint }}</span>@endif
    @error($key)<span class="error-text">{{ $message }}</span>@enderror
</div>
