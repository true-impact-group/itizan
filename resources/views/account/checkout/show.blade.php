@php
    $vat = $package->vatHalalas();
    $total = $package->chargeHalalas();
@endphp
<x-layouts.app :title="__('إتمام الاشتراك')">
    <x-page-header :title="__('إتمام الاشتراك')" :subtitle="__('راجع ما ستدفعه قبل المتابعة.')" />

    <div class="grid gap-6 lg:grid-cols-5">
        <form method="POST" action="{{ route('account.checkout.store', $package) }}" class="space-y-6 lg:col-span-3" data-once novalidate>
            @csrf

            <fieldset class="card">
                <legend class="text-xl font-semibold text-petrol-dark">{{ __('وسيلة الدفع') }}</legend>
                <div class="mt-4 space-y-3">
                    @foreach ($methods as $method)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 has-[:checked]:border-petrol has-[:checked]:bg-petrol-soft">
                            <input type="radio" name="payment_method" value="{{ $method->value }}" class="mt-1.5 size-5 accent-petrol" @checked(old('payment_method', $methods[0]->value) === $method->value) required>
                            <span>
                                <span class="font-semibold">{{ $method->label() }}</span>
                                @if ($method->isInstallment())
                                    <span class="block text-sm text-ink-soft">{{ __('تقسيط على :n دفعات متساوية، الدفعة التقريبية :amount. يعتمد على موافقة مزود الدفع وأهلية العميل. لا يدعم التجديد التلقائي؛ يكون التجديد بطلب جديد منك قبل انتهاء الاشتراك.', ['n' => config('itizan.payments.installments'), 'amount' => money($package->installmentHalalas())]) }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('payment_method')<span class="error-text">{{ $message }}</span>@enderror
            </fieldset>

            @if (blank(auth()->user()->email))
                <div class="card">
                    <x-input name="email" type="email" :label="__('البريد الإلكتروني')" required autocomplete="email" dir="ltr"
                             :hint="__('نحتاجه لإرسال الإيصال وتنبيهات الجرعات والمتابعة.')" />
                </div>
            @endif

            <div class="card space-y-4">
                <h2 class="text-xl">{{ __('الموافقات') }}</h2>
                {{-- Automatic renewal is a separate, optional consent. --}}
                <x-checkbox name="auto_renew" :label="__('أرغب بتجديد اشتراكي تلقائيًا عند انتهائه (اختياري)')"
                            :hint="__('موافقة مستقلة؛ يمكنك إلغاء التجديد التلقائي في أي وقت من حسابك، ويستمر اشتراكك حتى نهاية مدته.')" />
                <x-checkbox name="accept_terms">
                    <x-slot:label>
                        {{ __('اطلعت على المبلغ الإجمالي وما سيُخصم، وأوافق على') }}
                        @foreach ($policies as $policy)
                            <a href="{{ route('policies.show', $policy) }}" target="_blank" rel="noopener">{{ $policy->title() }}</a>@unless ($loop->last){{ is_rtl() ? '،' : ',' }} @endunless
                        @endforeach
                    </x-slot:label>
                </x-checkbox>
            </div>

            <button type="submit" class="btn btn-primary btn-block text-lg">{{ __('المتابعة إلى الدفع: :total', ['total' => money($total)]) }}</button>
        </form>

        <aside class="lg:col-span-2">
            <section class="card-sage" aria-labelledby="summary-title">
                <h2 id="summary-title" class="text-xl">{{ __('ملخص الطلب') }}</h2>
                <p class="mt-3 text-lg font-semibold">{{ $package->name() }}</p>
                <p class="text-ink-soft">{{ trans_choice('{6} 6 أشهر|{12} سنة|[*] :count أشهر', $package->duration_months, ['count' => $package->duration_months]) }} · {{ __('بمتوسط :amount شهريًا', ['amount' => money($package->monthlyAverageHalalas())]) }}</p>
                <dl class="mt-4 space-y-2">
                    <div class="flex justify-between"><dt>{{ __('قيمة الاشتراك') }}</dt><dd>{{ money($package->vat_inclusive ? $package->price_halalas - $vat : $package->price_halalas) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('ضريبة القيمة المضافة (:rate%)', ['rate' => config('itizan.vat_rate')]) }}</dt><dd>{{ money($vat) }}</dd></div>
                    <div class="flex justify-between border-t border-sage pt-2 text-lg font-bold"><dt>{{ __('الإجمالي المخصوم فعليًا') }}</dt><dd>{{ money($total) }}</dd></div>
                </dl>
                <p class="mt-3 text-sm">{{ $package->vat_inclusive ? __('السعر شامل ضريبة القيمة المضافة.') : __('السعر لا يشمل ضريبة القيمة المضافة، وقد أُضيفت أعلاه.') }}</p>
                <p class="mt-2 text-sm">{{ __('الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.') }}</p>
                <p class="mt-2 text-sm">{{ trans_choice('{0} لا تشمل الباقة استشارات متابعة.|{1} تشمل الباقة استشارة متابعة واحدة.|[2,*] تشمل الباقة :count استشارات متابعة.', $package->included_followups, ['count' => $package->included_followups]) }}</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
