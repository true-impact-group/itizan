@php
    use App\Enums\PaymentMethod;
    $installmentsOn = PaymentMethod::Tabby->isEnabled() || PaymentMethod::Tamara->isEnabled();
@endphp
<x-layouts.site :title="__('الأسعار والباقات')">
    <div class="section">
        <h1 class="text-4xl">{{ __('الأسعار والباقات') }}</h1>
        <p class="mt-3 max-w-3xl text-xl text-ink-soft">{{ __('تتوفر باقتان مدفوعتان فقط. تظهر لك قيمة الاشتراك الإجمالية وما سيُخصم فعليًا قبل الدفع.') }}</p>

        {{-- The free consultation comes before the packages. --}}
        <div class="mt-8"><x-free-consultation /></div>

        <div class="mt-10 grid gap-6 md:grid-cols-2">
            @foreach ($packages as $package)
                <x-package-card :package="$package" :savings="$savings" />
            @endforeach
        </div>

        <section class="mt-12" aria-labelledby="pay-title">
            <h2 id="pay-title" class="text-2xl">{{ __('خيارات الدفع') }}</h2>
            <ul class="mt-4 grid gap-3 md:grid-cols-2">
                <li class="card"><strong>{{ __('Apple Pay ومدى') }}</strong><p class="mt-1 text-ink-soft">{{ __('متاحان عند الشراء.') }}</p></li>
                <li class="card">
                    <strong>{{ __('تابي وتمارا') }}</strong>
                    <p class="mt-1 text-ink-soft">
                        @if ($installmentsOn)
                            {{ __('متاحان. التقسيط على أربع دفعات متساوية يعتمد على موافقة مزود الدفع وأهلية العميل.') }}
                        @else
                            {{ __('تُتاح بعد اعتماد حساب التاجر وقبول نوع الخدمة.') }}
                        @endif
                    </p>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($packages as $package)
                            <li>{{ __('الدفعة التقريبية للباقة «:name» عند التقسيط على أربع دفعات: :amount', ['name' => $package->name(), 'amount' => money($package->installmentHalalas())]) }}</li>
                        @endforeach
                    </ul>
                </li>
            </ul>
        </section>

        <section class="mt-12" aria-labelledby="terms-title">
            <h2 id="terms-title" class="text-2xl">{{ __('قبل الدفع') }}</h2>
            <ul class="mt-4 space-y-2">
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('تظهر لك قيمة الاشتراك الإجمالية وما سيُخصم فعليًا، مع توضيح شمول ضريبة القيمة المضافة من عدمه.') }}</li>
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.') }}</li>
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('إذا كان التجديد التلقائي مطبقًا فنطلب موافقة مستقلة عليه. وعند استخدام تابي أو تمارا نوضح لك آلية التجديد قبل إتمام الشراء.') }}</li>
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('عدد استشارات المتابعة المشمولة محدد في كل باقة. لا نعد باستشارات غير محدودة.') }}</li>
            </ul>
            <p class="mt-4">
                <a href="{{ route('policies.show', 'subscription-renewal-cancellation-refund') }}">{{ __('سياسة الاشتراك والتجديد والإلغاء والاسترداد') }}</a>
            </p>
        </section>
    </div>
</x-layouts.site>
