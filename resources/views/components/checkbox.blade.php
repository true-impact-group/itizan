@props(['name', 'label', 'checked' => false, 'value' => '1', 'hint' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace('.', '_', $key));
    $isChecked = old($key, $checked) ? true : false;
@endphp
<div>
    <label for="{{ $id }}" class="flex cursor-pointer items-start gap-3">
        <input type="hidden" name="{{ $name }}" value="0">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($isChecked)
               @if ($hint) aria-describedby="{{ $id }}-hint" @endif
               {{ $attributes->except('id')->merge(['class' => 'mt-1.5 size-5 shrink-0 rounded border-line accent-petrol']) }}>
        <span>{{ $label }}</span>
    </label>
    @if ($hint)<span id="{{ $id }}-hint" class="hint ms-8">{{ $hint }}</span>@endif
    @error($key)<span class="error-text ms-8">{{ $message }}</span>@enderror
</div>
