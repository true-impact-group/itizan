<x-layouts.app :title="__('المنتجات')">
    <x-page-header :title="__('المنتجات')" :subtitle="__('كتالوج الأدوية والمكملات. الأسعار تقديرية وتؤكدها الصيدلية.')">
        <x-slot:actions><a href="{{ route('admin.products.create') }}" class="btn btn-primary">{{ __('إضافة منتج') }}</a></x-slot:actions>
    </x-page-header>

    @if ($products->isEmpty())
        <x-empty :title="__('لا توجد منتجات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('المنتجات') }}</caption>
                <thead><tr><th scope="col">{{ __('الرمز') }}</th><th scope="col">{{ __('الاسم') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('السعر') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td><bdi>{{ $product->sku }}</bdi></td>
                        <td class="font-semibold">{{ $product->name_ar }}</td>
                        <td>{{ $product->type->label() }}@if ($product->requires_prescription) · {{ __('بوصفة') }}@endif</td>
                        <td>{{ money($product->price_halalas) }}</td>
                        <td>
                            <x-badge :tone="$product->is_active ? 'success' : 'neutral'" :label="$product->is_active ? __('فعّال') : __('غير فعّال')" />
                            <x-badge :tone="$product->in_stock ? 'info' : 'warning'" :label="$product->in_stock ? __('متوفر') : __('غير متوفر')" />
                        </td>
                        <td class="flex gap-2">
                            <a href="{{ route('admin.products.edit', $product) }}">{{ __('تعديل') }}</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-confirm="{{ __('حذف هذا المنتج؟') }}">@csrf @method('DELETE')<button type="submit" class="text-danger underline">{{ __('حذف') }}</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    @endif
</x-layouts.app>
