@php
    use App\Actions\Billing\StartCheckout;
    $canBuy = ! $subscription || $subscription->ends_at->lte(now()->addDays(StartCheckout::RENEWAL_WINDOW_DAYS));
@endphp
<x-layouts.app :title="__('الاشتراك والفواتير')">
    <x-page-header :title="__('الاشتراك والفواتير')" />

    @if ($subscription)
        <section class="card-sage" aria-labelledby="current-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="current-title" class="text-2xl">{{ $subscription->package->name() }}</h2>
                    <p class="text-ink-soft">{{ __('من :from إلى :to', ['from' => $subscription->starts_at->translatedFormat('j F Y'), 'to' => $subscription->ends_at->translatedFormat('j F Y')]) }}</p>
                </div>
                <x-badge :status="$subscription->status" />
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-sm text-ink-soft">{{ __('الأيام المتبقية') }}</dt><dd class="text-xl font-semibold">{{ $subscription->daysLeft() }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('استشارات المتابعة المستخدمة') }}</dt><dd class="text-xl font-semibold">{{ $subscription->followups_used }} / {{ $subscription->followups_included }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('وسيلة الدفع') }}</dt><dd class="text-xl font-semibold">{{ $subscription->payment_method?->label() ?? '—' }}</dd></div>
            </dl>
            <p class="mt-3 text-sm text-ink-soft">{{ __('الدواء والتحاليل غير مشمولين في الاشتراك ما لم يُذكر خلاف ذلك.') }}</p>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="card" aria-labelledby="renew-title">
                <h2 id="renew-title" class="text-xl">{{ __('التجديد') }}</h2>
                @if ($subscription->auto_renew)
                    <p class="mt-2"><x-badge tone="success" :label="__('التجديد التلقائي مفعّل')" /></p>
                    <p class="mt-2 text-ink-soft">{{ __('يستمر اشتراكك حتى نهاية مدته حتى لو ألغيت التجديد التلقائي.') }}</p>
                    <form method="POST" action="{{ route('account.subscription.auto-renew.disable') }}" class="mt-3" data-confirm="{{ __('إلغاء التجديد التلقائي؟') }}" data-once>
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">{{ __('إلغاء التجديد التلقائي') }}</button>
                    </form>
                @else
                    <p class="mt-2"><x-badge tone="neutral" :label="__('التجديد التلقائي غير مفعّل')" /></p>
                    @unless ($subscription->payment_method?->isInstallment())
                        <form method="POST" action="{{ route('account.subscription.auto-renew.enable') }}" class="mt-3 space-y-3" data-once novalidate>
                            @csrf
                            <x-checkbox name="auto_renew_consent" :label="__('أوافق على تجديد اشتراكي تلقائيًا عند انتهائه، وأفهم أنني أستطيع إلغاء التجديد في أي وقت من حسابي.')" />
                            <button type="submit" class="btn btn-secondary">{{ __('تفعيل التجديد التلقائي') }}</button>
                        </form>
                    @else
                        <p class="mt-2 text-ink-soft">{{ __('الدفع عبر تابي أو تمارا لا يدعم التجديد التلقائي. يمكنك تجديد اشتراكك بطلب جديد قبل انتهائه.') }}</p>
                    @endunless
                @endif
            </section>

            <section class="card" aria-labelledby="method-title">
                <h2 id="method-title" class="text-xl">{{ __('تحديث وسيلة الدفع') }}</h2>
                <form method="POST" action="{{ route('account.subscription.payment-method') }}" class="mt-3 space-y-3" data-once novalidate>
                    @csrf @method('PUT')
                    <x-select name="payment_method" :label="__('وسيلة الدفع للتجديد')" :options="collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" :value="$subscription->payment_method?->value" />
                    <button type="submit" class="btn btn-secondary">{{ __('حفظ') }}</button>
                </form>
            </section>
        </div>
    @else
        <x-empty :title="__('ليس لديك اشتراك فعّال')" :text="__('اختر الباقة المناسبة لمتابعة برنامجك بإشراف طبي. الاستشارة المجانية لا تلزمك بالاشتراك.')" />
    @endif

    @if ($upcomingSubscription)
        <p class="alert alert-info mt-6">{{ __('لديك اشتراك قادم يبدأ في :date.', ['date' => $upcomingSubscription->starts_at->translatedFormat('j F Y')]) }}</p>
    @endif

    <section class="mt-8" aria-labelledby="packages-title">
        <h2 id="packages-title" class="mb-4 text-xl">{{ $subscription ? __('تجديد اشتراكك') : __('الباقات المتاحة') }}</h2>
        @if ($canBuy)
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($packages as $package)
                    <x-package-card :package="$package" :savings="$savings" />
                @endforeach
            </div>
        @else
            <p class="text-ink-soft">{{ __('يمكنك تجديد اشتراكك قبل انتهائه بـ :days يومًا.', ['days' => StartCheckout::RENEWAL_WINDOW_DAYS]) }}</p>
        @endif
    </section>

    <section class="mt-8" aria-labelledby="invoices-title">
        <h2 id="invoices-title" class="mb-4 text-xl">{{ __('الفواتير والمدفوعات') }}</h2>
        @if ($payments->isEmpty())
            <p class="text-ink-soft">{{ __('لا توجد مدفوعات بعد.') }}</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">{{ __('الفواتير والمدفوعات') }}</caption>
                    <thead><tr><th scope="col">{{ __('رقم الإيصال') }}</th><th scope="col">{{ __('التاريخ') }}</th><th scope="col">{{ __('الباقة') }}</th><th scope="col">{{ __('المبلغ') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td><bdi>{{ $payment->invoice_number }}</bdi></td>
                            <td>{{ $payment->paid_at?->translatedFormat('j F Y') }}</td>
                            <td>{{ $payment->subscription->package->name() }}</td>
                            <td>{{ money($payment->amount_halalas) }}</td>
                            <td><x-badge :status="$payment->status" /></td>
                            <td><a href="{{ route('account.invoices.show', $payment) }}">{{ __('عرض') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        @endif
    </section>
</x-layouts.app>
