@php use App\Support\Money; @endphp
<x-layouts.app :title="__('الباقات والأسعار')">
    <x-page-header :title="__('الباقات والأسعار')" :subtitle="__('باقتان مدفوعتان فقط. تغيير السعر يسري على الاشتراكات الجديدة فقط.')" />

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($packages as $package)
            <article class="card">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-xl">{{ $package->name_ar }}</h2>
                    <x-badge :tone="$package->is_active ? 'success' : 'neutral'" :label="$package->is_active ? __('فعّالة') : __('غير فعّالة')" />
                </div>
                <p class="mt-2 text-3xl font-bold text-petrol-dark">{{ money($package->price_halalas) }}</p>
                <p class="text-ink-soft">{{ __(':n أشهر', ['n' => $package->duration_months]) }} · {{ __('بمتوسط :amount شهريًا', ['amount' => money($package->monthlyAverageHalalas())]) }}</p>
                <p class="text-ink-soft">{{ __('استشارات المتابعة المشمولة: :n', ['n' => $package->included_followups]) }} · {{ $package->vat_inclusive ? __('السعر شامل الضريبة') : __('السعر لا يشمل الضريبة') }}</p>
                <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-secondary btn-sm mt-4">{{ __('تعديل') }}</a>
            </article>
        @endforeach
    </div>
</x-layouts.app>
