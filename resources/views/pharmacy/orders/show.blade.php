@php use App\Enums\OrderStatus; @endphp
<x-layouts.app :title="__('طلب رقم :id', ['id' => $order->id])">
    <p class="mb-2"><a href="{{ route('pharmacy.orders.index') }}">← {{ __('طلبات الصيدلية') }}</a></p>
    <x-page-header :title="__('طلب رقم :id', ['id' => $order->id])"><x-slot:actions><x-badge :status="$order->status" /></x-slot:actions></x-page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card" aria-labelledby="client-title">
            <h2 id="client-title" class="text-xl">{{ __('العميل والتوصيل') }}</h2>
            <dl class="mt-3 space-y-2">
                <div><dt class="text-sm text-ink-soft">{{ __('الاسم') }}</dt><dd>{{ $order->user->name }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('المدينة') }}</dt><dd>{{ $order->delivery_city }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('العنوان') }}</dt><dd class="whitespace-pre-line">{{ $order->delivery_address }}</dd></div>
                @if ($order->notes)<div><dt class="text-sm text-ink-soft">{{ __('ملاحظات') }}</dt><dd>{{ $order->notes }}</dd></div>@endif
                <div><dt class="text-sm text-ink-soft">{{ __('توصيل مجاني') }}</dt><dd>{{ $order->free_delivery ? __('نعم') : __('لا') }}</dd></div>
            </dl>
        </section>

        <section class="card" aria-labelledby="rx-title">
            <h2 id="rx-title" class="text-xl">{{ __('الوصفة') }}</h2>
            @if ($order->prescription)
                @can('view', $order->prescription)
                    <dl class="mt-3 space-y-2">
                        <div><dt class="text-sm text-ink-soft">{{ __('الدواء') }}</dt><dd class="font-semibold">{{ $order->prescription->medication_name }}</dd></div>
                        <div><dt class="text-sm text-ink-soft">{{ __('الجرعة') }}</dt><dd>{{ $order->prescription->dose }}</dd></div>
                        <div><dt class="text-sm text-ink-soft">{{ __('تعليمات الطبيب') }}</dt><dd class="whitespace-pre-line">{{ $order->prescription->instructions ?: '—' }}</dd></div>
                    </dl>
                @endcan
            @else
                <p class="mt-2 text-ink-soft">{{ __('لا توجد وصفة مرتبطة (منتجات بدون وصفة).') }}</p>
            @endif
        </section>
    </div>

    {{-- Quote: availability, price and delivery time --}}
    @can('quote', $order)
        <form method="POST" action="{{ route('pharmacy.orders.quote', $order) }}" class="card mt-6 space-y-4" data-once novalidate>
            @csrf
            <h2 class="text-xl">{{ __('التوفر والسعر') }}</h2>
            <p class="text-sm text-ink-soft">{{ __('أكّد التوفر والسعر النهائي وموعد التوصيل. لا يبدأ التجهيز إلا بعد موافقة العميل على السعر.') }}</p>
            <div class="space-y-3">
                @foreach ($order->items as $item)
                    <div class="grid items-center gap-3 rounded-xl border border-line p-3 sm:grid-cols-3">
                        <p class="font-semibold">{{ $item->name }} × {{ $item->quantity }}</p>
                        <x-checkbox :name="'lines['.$item->id.'][available]'" :label="__('متوفر')" :checked="$item->is_available" />
                        <x-input :name="'lines['.$item->id.'][unit_price]'" type="number" step="0.01" min="0" :label="__('سعر الوحدة (ريال)')" :value="number_format($item->unit_price_halalas / 100, 2, '.', '')" />
                    </div>
                @endforeach
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="eta_at" type="datetime-local" :label="__('موعد التوصيل المتوقع')" :value="now()->addDay()->format('Y-m-d\TH:i')" required />
                @unless ($order->free_delivery)
                    <x-input name="delivery_fee" type="number" step="0.01" min="0" :label="__('رسوم التوصيل (ريال)')" :value="number_format(config('itizan.delivery.fee_halalas') / 100, 2, '.', '')" />
                @endunless
            </div>
            <button type="submit" class="btn btn-primary">{{ __('إرسال عرض السعر للعميل') }}</button>
        </form>
    @else
        <section class="card mt-6" aria-labelledby="items-title">
            <h2 id="items-title" class="text-xl">{{ __('المنتجات') }}</h2>
            <ul class="mt-3 divide-y divide-line">
                @foreach ($order->items as $item)
                    <li class="flex justify-between py-2"><span>{{ $item->name }} × {{ $item->quantity }}</span><span>{{ $item->is_available ? money($item->lineTotalHalalas()) : __('غير متوفر') }}</span></li>
                @endforeach
            </ul>
            <p class="mt-3 flex justify-between font-semibold"><span>{{ __('الإجمالي شامل التوصيل') }}</span><span>{{ money($order->total_halalas) }}</span></p>
            @if ($order->eta_at)<p class="mt-1 text-sm text-ink-soft">{{ __('موعد التوصيل المتوقع: :date', ['date' => $order->eta_at->translatedFormat('j F، h:i A')]) }}</p>@endif
        </section>
    @endcan

    {{-- Fulfilment --}}
    <div class="mt-6 flex flex-wrap gap-3">
        @if ($order->status === OrderStatus::Approved)
            <form method="POST" action="{{ route('pharmacy.orders.advance', $order) }}" data-once>@csrf<input type="hidden" name="to" value="preparing"><button class="btn btn-primary">{{ __('بدء التجهيز') }}</button></form>
        @elseif ($order->status === OrderStatus::Preparing)
            <form method="POST" action="{{ route('pharmacy.orders.advance', $order) }}" data-once>@csrf<input type="hidden" name="to" value="out_for_delivery"><button class="btn btn-primary">{{ __('تسليم لشريك التوصيل') }}</button></form>
        @elseif ($order->status === OrderStatus::OutForDelivery)
            <form method="POST" action="{{ route('pharmacy.orders.advance', $order) }}" data-once>@csrf<input type="hidden" name="to" value="delivered"><button class="btn btn-primary">{{ __('تأكيد التسليم') }}</button></form>
        @endif
    </div>

    @can('cancel', $order)
        @if (in_array($order->status, [OrderStatus::Requested, OrderStatus::Quoted], true))
            <form method="POST" action="{{ route('pharmacy.orders.reject', $order) }}" class="card mt-6 flex flex-wrap items-end gap-3" data-confirm="{{ __('رفض هذا الطلب؟') }}" data-once>
                @csrf
                <div class="min-w-64 flex-1"><x-input name="reason" :label="__('سبب الرفض')" required minlength="3" maxlength="255" /></div>
                <button type="submit" class="btn btn-danger">{{ __('رفض الطلب') }}</button>
            </form>
        @endif
    @endcan

    <section class="card mt-6" aria-labelledby="timeline-title">
        <h2 id="timeline-title" class="text-xl">{{ __('سجل الطلب') }}</h2>
        <ol class="mt-3 space-y-2 border-s-2 border-sage ps-4">
            @foreach ($order->events as $event)
                <li><span class="font-semibold">{{ $event->status->label() }}</span> — {{ $event->created_at->translatedFormat('j F، h:i A') }}</li>
            @endforeach
        </ol>
    </section>
</x-layouts.app>
