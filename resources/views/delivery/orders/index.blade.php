@php use App\Enums\OrderStatus; @endphp
<x-layouts.app :title="__('طلبات التوصيل')">
    <x-page-header :title="__('طلبات التوصيل')" :subtitle="__('تظهر هنا بيانات التوصيل فقط. لا تُعرض المعلومات الطبية.')" />

    @forelse ($orders as $order)
        <article class="card mb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold">{{ __('طلب رقم :id', ['id' => $order->id]) }} · {{ $order->pharmacy?->name }}</p>
                <x-badge :status="$order->status" />
            </div>
            <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                <div><dt class="text-sm text-ink-soft">{{ __('المستلم') }}</dt><dd>{{ $order->user->name }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الجوال') }}</dt><dd dir="ltr" class="text-start"><a href="tel:{{ $order->user->phone }}">{{ $order->user->phone }}</a></dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('المدينة') }}</dt><dd>{{ $order->delivery_city }}</dd></div>
                <div class="sm:col-span-3"><dt class="text-sm text-ink-soft">{{ __('العنوان') }}</dt><dd class="whitespace-pre-line">{{ $order->delivery_address }}</dd></div>
            </dl>
            <div class="mt-4 flex gap-3">
                @if ($order->status === OrderStatus::Preparing)
                    <form method="POST" action="{{ route('delivery.orders.advance', $order) }}" data-once>@csrf<input type="hidden" name="to" value="out_for_delivery"><button class="btn btn-primary btn-sm">{{ __('استلمت الطلب وخرجت للتوصيل') }}</button></form>
                @elseif ($order->status === OrderStatus::OutForDelivery)
                    <form method="POST" action="{{ route('delivery.orders.advance', $order) }}" data-confirm="{{ __('تأكيد تسليم الطلب للعميل؟') }}" data-once>@csrf<input type="hidden" name="to" value="delivered"><button class="btn btn-primary btn-sm">{{ __('تم التسليم') }}</button></form>
                @endif
            </div>
        </article>
    @empty
        <x-empty :title="__('لا توجد طلبات للتوصيل حاليًا')" />
    @endforelse
    {{ $orders->links() }}
</x-layouts.app>
