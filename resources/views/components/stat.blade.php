@props(['label', 'value', 'href' => null, 'tone' => 'sage'])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => ($tone === 'peach' ? 'card-peach' : 'card-sage').' block no-underline']) }}>
    <p class="text-sm text-ink-soft">{{ $label }}</p>
    <p class="text-3xl font-bold text-petrol-dark">{{ $value }}</p>
</{{ $tag }}>
