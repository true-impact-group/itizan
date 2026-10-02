@props(['light' => false])
{{-- Wordmark with a balanced, flowing path (progress + care), deliberately not a scale. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true" focusable="false">
        <rect width="36" height="36" rx="10" fill="{{ $light ? '#ffffff' : '#16645B' }}"/>
        <path d="M7 23c5.5-10 10-10 13.5-4.5S28 22 30 13" stroke="{{ $light ? '#16645B' : '#ffffff' }}" stroke-width="3" fill="none" stroke-linecap="round"/>
        <circle cx="30" cy="13" r="2.4" fill="#F3C6AD"/>
    </svg>
    <span class="text-2xl font-bold {{ $light ? 'text-white' : 'text-petrol' }}">{{ __('اتزان') }}</span>
</span>
