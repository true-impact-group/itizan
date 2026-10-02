@php
    $net = $payment->amount_halalas - $payment->vat_halalas;
    $company = config('itizan.company');
@endphp
<x-layouts.app :title="__('إيصال دفع')">
    <div class="no-print mb-4 flex flex-wrap gap-3">
        <a href="{{ route('account.subscription.show') }}" class="btn btn-secondary btn-sm">← {{ __('الاشتراك والفواتير') }}</a>
        <button type="button" data-print class="btn btn-primary btn-sm">{{ __('طباعة / حفظ PDF') }}</button>
    </div>

    <article class="card max-w-3xl" aria-labelledby="receipt-title">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-4">
            <div>
                <h1 id="receipt-title" class="text-2xl">{{ __('إيصال دفع') }}</h1>
                <p class="text-ink-soft">{{ __('رقم الإيصال') }}: <bdi>{{ $payment->invoice_number }}</bdi></p>
                <p class="text-ink-soft">{{ __('التاريخ') }}: {{ $payment->paid_at?->translatedFormat('j F Y') }}</p>
            </div>
            <div class="text-end">
                <x-logo />
                <p class="mt-1 text-sm">{{ $company['name'] }}</p>
                @if ($company['vat_number'])<p class="text-sm text-ink-soft">{{ __('الرقم الضريبي') }}: <bdi>{{ $company['vat_number'] }}</bdi></p>@endif
            </div>
        </header>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div><dt class="text-sm text-ink-soft">{{ __('العميل') }}</dt><dd class="font-semibold">{{ $payment->user->name }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('وسيلة الدفع') }}</dt><dd class="font-semibold">{{ $payment->method->label() }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الباقة') }}</dt><dd class="font-semibold">{{ $payment->subscription->package->name() }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('مدة الاشتراك') }}</dt><dd class="font-semibold">{{ $payment->subscription->starts_at->translatedFormat('j F Y') }} — {{ $payment->subscription->ends_at->translatedFormat('j F Y') }}</dd></div>
        </dl>

        <table class="table mt-6">
            <tbody>
                <tr><th scope="row">{{ __('قيمة الاشتراك') }}</th><td class="text-end">{{ money($net) }}</td></tr>
                <tr><th scope="row">{{ __('ضريبة القيمة المضافة (:rate%)', ['rate' => config('itizan.vat_rate')]) }}</th><td class="text-end">{{ money($payment->vat_halalas) }}</td></tr>
                <tr><th scope="row" class="text-lg">{{ __('الإجمالي المدفوع') }}</th><td class="text-end text-lg font-bold">{{ money($payment->amount_halalas) }}</td></tr>
                @if ($payment->refunded_halalas)
                    <tr><th scope="row">{{ __('المبلغ المسترد') }}</th><td class="text-end">{{ money($payment->refunded_halalas) }}</td></tr>
                @endif
            </tbody>
        </table>
        <p class="mt-4 text-sm text-ink-soft">{{ __('هذا إيصال دفع للاشتراك. الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.') }}</p>
    </article>
</x-layouts.app>
