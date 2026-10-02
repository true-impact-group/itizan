@php use App\Enums\OrderStatus; @endphp
<x-layouts.app :title="__('طلب رقم :id', ['id' => $order->id])">
    <p class="mb-2"><a href="{{ route('account.orders.index') }}">← {{ __('طلباتي') }}</a></p>
    <x-page-header :title="__('طلب رقم :id', ['id' => $order->id])">
        <x-slot:actions><x-badge :status="$order->status" /></x-slot:actions>
    </x-page-header>

    {{-- Price approval --}}
    @if ($order->status === OrderStatus::Quoted)
        <section class="card-peach mb-6" aria-labelledby="quote-title">
            <h2 id="quote-title" class="text-xl">{{ __('راجع السعر ووافق عليه') }}</h2>
            <p class="mt-1">{{ __('أكدت الصيدلية التوفر والسعر. لن يبدأ التجهيز إلا بموافقتك.') }}</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                <div><dt class="text-sm text-ink-soft">{{ __('إجمالي المنتجات') }}</dt><dd class="font-semibold">{{ money($order->subtotal_halalas) }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('التوصيل') }}</dt><dd class="font-semibold">{{ $order->delivery_fee_halalas ? money($order->delivery_fee_halalas) : __('مجاني') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الإجمالي') }}</dt><dd class="text-xl font-bold">{{ money($order->total_halalas) }}</dd></div>
            </dl>
            @if ($order->eta_at)<p class="mt-2">{{ __('موعد التوصيل المتوقع: :date', ['date' => $order->eta_at->translatedFormat('l j F، h:i A')]) }}</p>@endif

            <form method="POST" action="{{ route('account.orders.approve', $order) }}" class="mt-4 space-y-3" data-once novalidate>
                @csrf
                <input type="hidden" name="total_halalas" value="{{ $order->total_halalas }}">
                <x-checkbox name="agree" :label="__('أوافق على السعر الإجمالي :total وعلى تنفيذ الطلب.', ['total' => money($order->total_halalas)])" />
                <button type="submit" class="btn btn-primary">{{ __('موافقة وتأكيد الطلب') }}</button>
            </form>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card" aria-labelledby="items-title">
            <h2 id="items-title" class="text-xl">{{ __('المنتجات') }}</h2>
            <ul class="mt-3 divide-y divide-line">
                @foreach ($order->items as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>{{ $item->name }} × {{ $item->quantity }}
                            @unless ($item->is_available)<x-badge tone="danger" :label="__('غير متوفر')" />@endunless</span>
                        <span>{{ $item->is_available ? money($item->lineTotalHalalas()) : '—' }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($order->total_halalas)
                <p class="mt-3 flex justify-between font-semibold"><span>{{ __('الإجمالي') }}</span><span>{{ money($order->total_halalas) }}</span></p>
            @endif
            <dl class="mt-4 text-sm text-ink-soft">
                <dt class="inline font-semibold">{{ __('التوصيل إلى') }}:</dt> <dd class="inline">{{ $order->delivery_city }}</dd>
                @if ($order->pharmacy)<br><dt class="inline font-semibold">{{ __('الصيدلية') }}:</dt> <dd class="inline">{{ $order->pharmacy->name }}</dd>@endif
                @if ($order->eta_at)<br><dt class="inline font-semibold">{{ __('موعد التوصيل المتوقع') }}:</dt> <dd class="inline">{{ $order->eta_at->translatedFormat('j F، h:i A') }}</dd>@endif
            </dl>
        </section>

        <section class="card" aria-labelledby="timeline-title">
            <h2 id="timeline-title" class="text-xl">{{ __('تحديثات الطلب') }}</h2>
            <ol class="mt-3 space-y-3 border-s-2 border-sage ps-4">
                @foreach ($order->events as $event)
                    <li>
                        <p class="font-semibold">{{ $event->status->label() }}</p>
                        @if ($event->note)<p class="text-ink-soft">{{ __($event->note) }}</p>@endif
                        <p class="text-sm text-ink-soft">{{ $event->created_at->translatedFormat('j F، h:i A') }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    @can('cancel', $order)
        @if ($order->canBeCancelledByClient())
            <form method="POST" action="{{ route('account.orders.cancel', $order) }}" class="mt-6" data-confirm="{{ __('هل تريد إلغاء هذا الطلب؟') }}" data-once>
                @csrf
                <button type="submit" class="btn btn-danger">{{ __('إلغاء الطلب') }}</button>
            </form>
        @endif
    @endcan
</x-layouts.app>
