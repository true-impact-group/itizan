{{-- Minimal, dependency-free error page: no session, auth or database access, so it can never fail itself. --}}
<x-layouts.base :title="$heading">
    <main id="main" class="flex flex-1 items-center justify-center px-4 py-16">
        <div class="card max-w-xl text-center">
            <x-logo class="mb-4" />
            <p class="text-6xl font-bold text-petrol" aria-hidden="true">{{ $code }}</p>
            <h1 class="mt-2 text-2xl">{{ $heading }}</h1>
            <p class="mt-3 text-lg text-ink-soft">{{ $message }}</p>

            @if (isset($requestId) && $requestId)
                <p class="mt-3 text-sm text-ink-soft">{{ __('رقم المرجع للدعم:') }} <bdi>{{ $requestId }}</bdi></p>
            @endif

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ url('/') }}" class="btn btn-primary">{{ __('العودة إلى الرئيسية') }}</a>
                <a href="{{ \App\Support\Whatsapp::url() }}" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">{{ __('تواصل معنا عبر واتساب') }}</a>
            </div>
        </div>
    </main>
</x-layouts.base>
