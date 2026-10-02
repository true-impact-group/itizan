@props(['title', 'subtitle' => null])
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-ink-soft">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
