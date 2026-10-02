<x-layouts.app :title="__('طلبات الصيدلية')">
    <x-page-header :title="__('طلبات الصيدلية')" :subtitle="auth()->user()->pharmacy?->name" />

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('تصفية') }}">
        @foreach (['new' => 'جديدة وبانتظار الموافقة', 'active' => 'قيد التنفيذ', 'done' => 'المنتهية'] as $key => $label)
            <a href="{{ route('pharmacy.orders.index', ['tab' => $key]) }}" class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-secondary' }}" @if ($tab === $key) aria-current="page" @endif>{{ __($label) }}</a>
        @endforeach
    </nav>

    @if ($orders->isEmpty())
        <x-empty :title="__('لا توجد طلبات هنا')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('طلبات الصيدلية') }}</caption>
                <thead><tr><th scope="col">{{ __('رقم الطلب') }}</th><th scope="col">{{ __('المنتجات') }}</th><th scope="col">{{ __('المدينة') }}</th><th scope="col">{{ __('الطلب') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->items->map(fn ($item) => $item->name.' × '.$item->quantity)->implode('، ') }}</td>
                        <td>{{ $order->delivery_city }}</td>
                        <td>{{ $order->created_at->diffForHumans() }}</td>
                        <td><x-badge :status="$order->status" /></td>
                        <td><a href="{{ route('pharmacy.orders.show', $order) }}">{{ __('فتح') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    @endif
</x-layouts.app>
