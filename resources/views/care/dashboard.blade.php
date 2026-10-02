<x-layouts.app :title="__('لوحة فريق الرعاية')">
    <x-page-header :title="__('لوحة فريق الرعاية')" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('care.contact-requests.index') }}" class="card-sage no-underline"><p class="text-sm text-ink-soft">{{ __('طلبات تواصل مفتوحة') }}</p><p class="text-3xl font-bold text-petrol-dark">{{ $openRequests }}</p></a>
        <a href="{{ route('care.contact-requests.index') }}" class="card-peach no-underline"><p class="text-sm text-ink-soft">{{ __('طلبات عاجلة') }}</p><p class="text-3xl font-bold text-danger">{{ $urgentRequests }}</p></a>
        <a href="{{ route('care.side-effects.index') }}" class="card-sage no-underline"><p class="text-sm text-ink-soft">{{ __('أعراض جانبية جديدة') }}</p><p class="text-3xl font-bold text-petrol-dark">{{ $newSideEffects }}</p></a>
        <a href="{{ route('care.side-effects.index', ['status' => 'escalated']) }}" class="card-sage no-underline"><p class="text-sm text-ink-soft">{{ __('مصعّدة للطبيب') }}</p><p class="text-3xl font-bold text-petrol-dark">{{ $escalated }}</p></a>
    </div>

    <section class="card" aria-labelledby="latest-title">
        <h2 id="latest-title" class="text-xl">{{ __('أقدم الطلبات المفتوحة') }}</h2>
        @forelse ($latestRequests as $contact)
            <p class="mt-3">
                @if ($contact->isUrgent())<x-badge tone="danger" :label="__('عاجل')" />@endif
                <strong>{{ $contact->user->name }}</strong> · {{ $contact->target->label() }} · {{ $contact->created_at->diffForHumans() }}
            </p>
        @empty
            <p class="mt-2 text-ink-soft">{{ __('لا توجد طلبات مفتوحة. أحسنتم!') }}</p>
        @endforelse
    </section>
</x-layouts.app>
