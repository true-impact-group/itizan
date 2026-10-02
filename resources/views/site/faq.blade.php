<x-layouts.site :title="__('الأسئلة الشائعة')">
    <div class="section max-w-4xl">
        <h1 class="text-4xl">{{ __('الأسئلة الشائعة') }}</h1>
        <div class="mt-8 space-y-3">
            @forelse ($faqs as $faq)
                <details class="card">
                    <summary class="cursor-pointer text-lg font-semibold text-petrol-dark">{{ $faq->question() }}</summary>
                    <p class="mt-3 text-lg">{{ $faq->answer() }}</p>
                </details>
            @empty
                <x-empty :title="__('لا توجد أسئلة بعد')" />
            @endforelse
        </div>
        <p class="mt-8 text-lg">
            {{ __('لم تجد إجابتك؟') }}
            <a href="{{ route('contact') }}">{{ __('تواصل معنا') }}</a>
        </p>
    </div>
</x-layouts.site>
