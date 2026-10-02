@php use App\Enums\ProductType; @endphp
<x-layouts.app :title="$product ? __('تعديل منتج') : __('إضافة منتج')">
    <p class="mb-2"><a href="{{ route('admin.products.index') }}">← {{ __('المنتجات') }}</a></p>
    <x-page-header :title="$product ? __('تعديل منتج') : __('إضافة منتج')" />

    <form method="POST" action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card max-w-3xl space-y-5" data-once novalidate>
        @csrf
        @if ($product) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-3">
            <x-input name="sku" :label="__('رمز المنتج')" :value="$product?->sku" required dir="ltr" />
            <x-select name="type" :label="__('النوع')" :options="ProductType::options()" :value="$product?->type?->value" :placeholder="__('اختر')" required
                      :hint="__('الأدوية تُصرف بوصفة دائمًا.')" />
            <x-input name="price" type="number" step="0.01" min="0" :label="__('السعر التقديري (ريال)')" :value="$product ? number_format($product->price_halalas / 100, 2, '.', '') : null" required />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="name_ar" :label="__('الاسم (عربي)')" :value="$product?->name_ar" required />
            <x-input name="name_en" :label="__('الاسم (إنجليزي)')" :value="$product?->name_en" required dir="ltr" />
            <x-textarea name="description_ar" :label="__('الوصف (عربي)')" rows="2" :value="$product?->description_ar" />
            <x-textarea name="description_en" :label="__('الوصف (إنجليزي)')" rows="2" :value="$product?->description_en" dir="ltr" />
        </div>
        <x-checkbox name="in_stock" :label="__('متوفر')" :checked="$product ? $product->in_stock : true" />
        <x-checkbox name="is_active" :label="__('ظاهر للطلب')" :checked="$product ? $product->is_active : true" />
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
