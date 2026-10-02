<x-layouts.app :title="__('طلباتي')">
    <x-page-header :title="__('طلبات الأدوية والمكملات')">
        <x-slot:actions><a href="{{ route('account.orders.create') }}" class="btn btn-primary">{{ __('طلب جديد') }}</a></x-slot:actions>
    </x-page-header>

    @if ($orders->isEmpty())
        <x-empty :title="__('لا توجد طلبات')" :text="__('اطلب دواءك أو مكملاتك وتابع التوصيل من هنا.')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('طلباتي') }}</caption>
                <thead><tr><th scope="col">{{ __('رقم الطلب') }}</th><th scope="col">{{ __('التاريخ') }}</th><th scope="col">{{ __('المنتجات') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->created_at->translatedFormat('j F Y') }}</td>
                        <td>{{ $order->items->map(fn ($item) => $item->name.' × '.$item->quantity)->implode('، ') }}</td>
                        <td><x-badge :status="$order->status" /></td>
                        <td><a href="{{ route('account.orders.show', $order) }}">{{ __('التفاصيل') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    @endif
</x-layouts.app>
