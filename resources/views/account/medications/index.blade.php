<x-layouts.app :title="__('الأدوية والجرعات')">
    <x-page-header :title="__('الأدوية والجرعات')" :subtitle="__('الدواء والجرعة يحددهما الطبيب وحده. هنا تتابع جرعاتك وتسجلها.')">
        <x-slot:actions>
            <a href="{{ route('account.side-effects.index') }}" class="btn btn-secondary">{{ __('تسجيل عَرَض جانبي') }}</a>
            <a href="{{ route('account.orders.create') }}" class="btn btn-primary">{{ __('طلب دواء أو مكمل') }}</a>
        </x-slot:actions>
    </x-page-header>

    @unless ($hasSubscription)
        <div class="alert alert-warning mb-6" role="status">
            {{ __('اشتراكك غير فعّال. تبقى متابعة جرعاتك متاحة، لكن طلب الدواء يحتاج إلى اشتراك فعّال، وسيراجع طبيبك وصفتك.') }}
            <a href="{{ route('account.subscription.show') }}" class="font-bold">{{ __('جدّد اشتراكك') }}</a>
        </div>
    @endunless

    @if ($prescriptions->isEmpty())
        <x-empty :title="__('لا توجد وصفات في حسابك')" :text="__('إذا رأى الطبيب أن العلاج مناسب لك بعد تقييمك، ستظهر وصفتك هنا.')" />
    @else
        <div class="grid gap-4">
            @foreach ($prescriptions as $prescription)
                <article class="card flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl">{{ $prescription->medication_name }}</h2>
                        <p class="text-ink-soft">{{ $prescription->dose }} · {{ trans_choice('{1} كل يوم|{7} كل أسبوع|[*] كل :count يومًا', $prescription->interval_days, ['count' => $prescription->interval_days]) }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ __('الطبيب') }}: {{ $prescription->practitioner->displayName() }} · {{ __('بدأت في :date', ['date' => $prescription->starts_on->translatedFormat('j F Y')]) }}</p>
                    </div>
                    <div class="text-end">
                        <x-badge :status="$prescription->status" />
                        @if ($prescription->next_dose_at)
                            <p class="mt-2 text-sm">{{ __('الجرعة القادمة') }}: {{ $prescription->next_dose_at->translatedFormat('j F، h:i A') }}</p>
                        @endif
                        <a href="{{ route('account.medications.show', $prescription) }}" class="mt-2 inline-block">{{ __('التفاصيل') }}</a>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $prescriptions->links() }}
    @endif
</x-layouts.app>
