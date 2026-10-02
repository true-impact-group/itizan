@props(['package', 'savings' => null])
@php
    $isBestValue = $savings && $savings['package']->is($package);
    $installments = \App\Enums\PaymentMethod::Tabby->isEnabled() || \App\Enums\PaymentMethod::Tamara->isEnabled();
    $cta = auth()->check() && auth()->user()->isClient()
        ? route('account.checkout.show', $package)
        : route('register');
@endphp
<article class="{{ $isBestValue ? 'card border-2 border-petrol' : 'card' }} flex h-full flex-col" aria-labelledby="pkg-{{ $package->id }}">
    <div class="flex items-start justify-between gap-3">
        <h3 id="pkg-{{ $package->id }}" class="text-xl">{{ $package->name() }}</h3>
        @if ($isBestValue)
            <span class="badge badge-success">{{ __('الأوفر') }}</span>
        @endif
    </div>
    @if ($package->description())
        <p class="mt-1 text-ink-soft">{{ $package->description() }}</p>
    @endif

    <p class="mt-4 flex items-baseline gap-2">
        <span class="text-4xl font-bold text-petrol-dark">{{ money($package->price_halalas, false) }}</span>
        <span class="text-lg font-medium">{{ __('ريال') }}</span>
        <span class="text-ink-soft">/ {{ trans_choice('{6} 6 أشهر|{12} سنة|[*] :count أشهر', $package->duration_months, ['count' => $package->duration_months]) }}</span>
    </p>
    <p class="mt-1 font-medium text-petrol">
        {{ __('بمتوسط :amount شهريًا', ['amount' => money($package->monthlyAverageHalalas())]) }}
    </p>
    @if ($isBestValue)
        <p class="mt-1 text-success font-medium">
            {{ __('يوفر :amount مقارنة بشراء باقات أقصر بالسعر نفسه، أي خصم يقارب :percent%.', ['amount' => money($savings['halalas']), 'percent' => $savings['percent']]) }}
        </p>
    @endif

    <ul class="mt-4 space-y-2">
        <li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ __('استشارة مجانية قبل الاشتراك للعملاء الجدد') }}</span></li>
        <li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ __('تقييم طبي وخطة إدارة وزن بإشراف الطبيب') }}</span></li>
        <li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ trans_choice('{0} لا تشمل استشارات متابعة|{1} استشارة متابعة واحدة مشمولة|[2,10] :count استشارات متابعة مشمولة|[11,*] :count استشارة متابعة مشمولة', $package->included_followups, ['count' => $package->included_followups]) }}</span></li>
        @foreach ($package->features() as $feature)
            <li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ $feature }}</span></li>
        @endforeach
    </ul>

    <div class="mt-auto pt-5">
        <p class="mb-3 text-sm text-ink-soft">
            {{ $package->vat_inclusive ? __('السعر شامل ضريبة القيمة المضافة.') : __('السعر لا يشمل ضريبة القيمة المضافة، وتُضاف عند الدفع.') }}
            {{ __('الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.') }}
        </p>
        @if ($installments)
            <p class="mb-3 text-sm text-ink-soft">
                {{ __('تقسيط على :n دفعات متساوية، الدفعة التقريبية :amount، ويعتمد على موافقة مزود الدفع وأهلية العميل.', ['n' => config('itizan.payments.installments'), 'amount' => money($package->installmentHalalas())]) }}
            </p>
        @endif
        <a href="{{ $cta }}" class="btn btn-primary btn-block">{{ __('اختر هذه الباقة') }}</a>
    </div>
</article>
