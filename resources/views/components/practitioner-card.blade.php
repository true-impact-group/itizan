@props(['practitioner'])
{{-- Only verified practitioners reach this component; name, specialty and licence are shown after verification. --}}
<article class="card">
    <div class="mb-3 flex size-14 items-center justify-center rounded-full bg-sage text-xl font-bold text-petrol-dark" aria-hidden="true">
        {{ mb_substr($practitioner->displayName(), 0, 1) }}
    </div>
    <h3 class="text-lg">{{ $practitioner->displayName() }}</h3>
    <p class="text-ink-soft">{{ $practitioner->specialty() }}</p>
    <p class="mt-2 text-sm">
        <span class="badge badge-success">{{ __('ترخيص موثّق') }}</span>
    </p>
    <p class="mt-2 text-sm text-ink-soft">
        {{ $practitioner->license_authority }} · {{ __('رقم الترخيص') }}: <bdi>{{ $practitioner->license_number }}</bdi>
    </p>
    @if ($practitioner->years_experience)
        <p class="text-sm text-ink-soft">{{ __(':years سنة خبرة', ['years' => $practitioner->years_experience]) }}</p>
    @endif
    @if ($practitioner->bio())
        <p class="mt-2 text-sm">{{ $practitioner->bio() }}</p>
    @endif
</article>
