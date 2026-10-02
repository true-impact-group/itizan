@props(['title', 'text' => null])
<div {{ $attributes->merge(['class' => 'card-sage text-center']) }}>
    <p class="text-lg font-semibold text-petrol-dark">{{ $title }}</p>
    @if ($text)<p class="mx-auto mt-1 max-w-xl text-ink-soft">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
