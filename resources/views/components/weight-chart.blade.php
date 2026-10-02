@props(['points', 'target' => null])
{{--
    Server-rendered SVG line chart (no JavaScript library). $points is a collection of measurements ordered oldest → newest.
    A table below the chart in the page gives the same numbers, so the chart is a convenience, not the only view.
--}}
@php
    $values = $points->pluck('weight_kg')->map(fn ($v) => (float) $v)->values();
    $count = $values->count();
@endphp
@if ($count >= 2)
    @php
        $w = 640; $h = 220; $padX = 16; $padY = 24;
        $all = $target !== null ? $values->concat([(float) $target]) : $values;
        $min = floor($all->min() - 1);
        $max = ceil($all->max() + 1);
        $span = max($max - $min, 1);
        $x = fn (int $i) => $padX + ($w - 2 * $padX) * ($i / ($count - 1));
        $y = fn (float $v) => $padY + ($h - 2 * $padY) * (1 - ($v - $min) / $span);
        $path = $values->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
        $first = $points->first()->measured_on->translatedFormat('j M Y');
        $last = $points->last()->measured_on->translatedFormat('j M Y');
    @endphp
    <figure>
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img"
             aria-label="{{ __('مخطط الوزن من :from إلى :to، من :start كجم إلى :end كجم', ['from' => $first, 'to' => $last, 'start' => $values->first(), 'end' => $values->last()]) }}">
            <rect x="0" y="0" width="{{ $w }}" height="{{ $h }}" rx="16" fill="#E6EEE7"/>
            @if ($target !== null)
                <line x1="{{ $padX }}" x2="{{ $w - $padX }}" y1="{{ $y((float) $target) }}" y2="{{ $y((float) $target) }}" stroke="#E9A68F" stroke-width="2" stroke-dasharray="6 6"/>
                <text x="{{ is_rtl() ? $w - $padX : $padX }}" y="{{ $y((float) $target) - 6 }}" text-anchor="{{ is_rtl() ? 'end' : 'start' }}" font-size="13" fill="#7a4a00">{{ __('الهدف') }} {{ $target }}</text>
            @endif
            <polyline points="{{ $path }}" fill="none" stroke="#176B63" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
            @foreach ($values as $i => $v)
                <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($v), 1) }}" r="4" fill="#176B63"/>
            @endforeach
        </svg>
        <figcaption class="mt-2 flex justify-between text-sm text-ink-soft"><span>{{ $first }}</span><span>{{ $last }}</span></figcaption>
    </figure>
@else
    <p class="text-ink-soft">{{ __('سجّل قياسين على الأقل ليظهر مخطط تقدمك.') }}</p>
@endif
