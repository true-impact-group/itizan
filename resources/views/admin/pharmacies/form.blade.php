<x-layouts.app :title="$pharmacy ? __('تعديل صيدلية') : __('إضافة صيدلية')">
    <p class="mb-2"><a href="{{ route('admin.pharmacies.index') }}">← {{ __('الصيدليات') }}</a></p>
    <x-page-header :title="$pharmacy ? __('تعديل صيدلية') : __('إضافة صيدلية')" />

    <form method="POST" action="{{ $pharmacy ? route('admin.pharmacies.update', $pharmacy) : route('admin.pharmacies.store') }}" class="card max-w-2xl space-y-5" data-once novalidate>
        @csrf
        @if ($pharmacy) @method('PUT') @endif
        <x-input name="name" :label="__('اسم الصيدلية')" :value="$pharmacy?->name" required />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="license_number" :label="__('رقم الترخيص')" :value="$pharmacy?->license_number" required dir="ltr" />
            <x-select name="city" :label="__('المدينة')" :options="array_combine(config('itizan.cities'), config('itizan.cities'))" :value="$pharmacy?->city" :placeholder="__('اختر')" required />
        </div>
        <x-input name="phone" type="tel" :label="__('هاتف الصيدلية')" :value="$pharmacy?->phone" dir="ltr" />
        <x-checkbox name="is_active" :label="__('فعّالة')" :checked="$pharmacy ? $pharmacy->is_active : true" />
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
