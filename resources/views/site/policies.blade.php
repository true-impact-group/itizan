<x-layouts.site :title="__('السياسات والشروط')">
    <div class="section max-w-4xl">
        <h1 class="text-4xl">{{ __('السياسات والشروط') }}</h1>
        <p class="mt-3 text-xl text-ink-soft">{{ __('نوضح لك حقوقك والتزاماتنا بلغة واضحة.') }}</p>
        <ul class="mt-8 grid gap-3 md:grid-cols-2">
            @foreach ($pages as $page)
                <li><a href="{{ route('policies.show', $page) }}" class="card block no-underline hover:border-petrol">
                    <span class="text-lg font-semibold">{{ $page->title() }}</span>
                </a></li>
            @endforeach
        </ul>
    </div>
</x-layouts.site>
