@props(['name', 'size' => 24])
@php
    // One consistent stroke-icon set (24px grid, round caps). Decorative: the text next to it carries the meaning.
    $paths = [
        'heart' => 'M12 20s-7-4.4-7-10a4 4 0 017-2.4A4 4 0 0119 10c0 5.6-7 10-7 10z',
        'plan' => 'M7 4h10a2 2 0 012 2v14a1 1 0 01-1 1H6a1 1 0 01-1-1V6a2 2 0 012-2zM9 4V3h6v1M9 11h6M9 15h4',
        'chat' => 'M4 6a3 3 0 013-3h10a3 3 0 013 3v8a3 3 0 01-3 3H11l-5 4v-4a3 3 0 01-2-3z',
        'steth' => 'M6 3v6a4 4 0 008 0V3M10 13v2a5 5 0 0010 0v-1M20 10a2 2 0 100 4 2 2 0 000-4z',
        'leaf' => 'M5 19c0-9 5-14 15-14 0 10-5 15-14 15M5 19c3-4 6-6 10-8',
        'trend' => 'M4 17l5-5 4 4 7-8M15 8h5v5',
        'box' => 'M4 8l8-4 8 4-8 4zM4 8v8l8 4 8-4V8M12 12v8',
        'whatsapp' => 'M4 20l1.2-4A8 8 0 118 19zM9 9c0 3 3 6 6 6l1-1.5-2-1-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1-2z',
        'clock' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
        'question' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM9.5 9.5a2.5 2.5 0 114 2c-1 .8-1.5 1.2-1.5 2.5M12 17h.01',
        'hand' => 'M12 21c-5-3-8-6-8-10a4 4 0 018-1 4 4 0 018 1c0 4-3 7-8 10z',
        'check' => 'M5 12.5l4.5 4.5L19 7.5',
        'minus' => 'M6 12h12',
        'arrow' => 'M15 6l-6 6 6 6',
        'home' => 'M4 11l8-7 8 7v9H4z',
        'calendar' => 'M7 5h10a3 3 0 013 3v9a3 3 0 01-3 3H7a3 3 0 01-3-3V8a3 3 0 013-3zM8 3v4M16 3v4M4 10h16',
    ];
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes }}><path d="{{ $paths[$name] ?? '' }}"/></svg>
