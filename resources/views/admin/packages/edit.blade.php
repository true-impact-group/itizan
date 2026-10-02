@php
    $featuresAr = implode("\n", $package->features_ar ?? []);
    $featuresEn = implode("\n", $package->features_en ?? []);
@endphp
<x-layouts.app :title="__('تعديل الباقة')">
    <p class="mb-2"><a href="{{ route('admin.packages.index') }}">← {{ __('الباقات والأسعار') }}</a></p>
    <x-page-header :title="$package->name_ar" />

    <form method="POST" action="{{ route('admin.packages.update', $package) }}" class="card max-w-3xl space-y-5" data-once novalidate>
        @csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="name_ar" :label="__('الاسم (عربي)')" :value="$package->name_ar" required />
            <x-input name="name_en" :label="__('الاسم (إنجليزي)')" :value="$package->name_en" required dir="ltr" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-textarea name="description_ar" :label="__('الوصف (عربي)')" rows="2" :value="$package->description_ar" />
            <x-textarea name="description_en" :label="__('الوصف (إنجليزي)')" rows="2" :value="$package->description_en" dir="ltr" />
        </div>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-input name="price" type="number" step="0.01" min="1" :label="__('السعر (ريال)')" :value="number_format($package->price_halalas / 100, 2, '.', '')" required />
            <x-input name="included_followups" type="number" min="0" max="60" :label="__('استشارات المتابعة المشمولة')" :value="$package->included_followups" required
                     :hint="__('المقترح: 3 للنصف سنوي و6 للسنوي، بعد التحقق من التكلفة وقدرة الفريق.')" />
            <x-input name="sort_order" type="number" min="0" :label="__('الترتيب')" :value="$package->sort_order" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-textarea name="features_ar" :label="__('مميزات إضافية (سطر لكل ميزة) — عربي')" rows="3" :value="$featuresAr" />
            <x-textarea name="features_en" :label="__('مميزات إضافية — إنجليزي')" rows="3" :value="$featuresEn" dir="ltr" />
        </div>
        <x-checkbox name="vat_inclusive" :label="__('السعر شامل ضريبة القيمة المضافة')" :checked="$package->vat_inclusive" :hint="__('إن لم يكن شاملًا، تُضاف الضريبة عند الدفع.')" />
        <x-checkbox name="is_active" :label="__('الباقة متاحة للشراء')" :checked="$package->is_active" />
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
