@props(['status' => null, 'tone' => null, 'label' => null])
@php
    // Accepts a status enum (label + tone come from the enum) or an explicit tone/label.
    $tone ??= $status && method_exists($status, 'tone') ? $status->tone() : 'neutral';
    $label ??= $status?->label();
@endphp
<span {{ $attributes->merge(['class' => "badge badge-{$tone}"]) }}>{{ $label ?? $slot }}</span>
