@php use App\Enums\OrderStatus; @endphp
<x-layouts.app :title="__('متابعة العمليات')">
    <x-page-header :title="__('متابعة الوصفات والتحاليل والتوصيل')" :subtitle="__('أعداد وحالات فقط، دون أي محتوى طبي.')" />

    <div class="mb-6 grid gap-6 md:grid-cols-2">
        <section class="card-sage" aria-labelledby="rx-title">
            <h2 id="rx-title" class="text-xl">{{ __('الوصفات') }}</h2>
            <dl class="mt-3 space-y-1">
                @foreach ($prescriptionStatuses as $s)<div class="flex justify-between"><dt>{{ $s->label() }}</dt><dd class="font-semibold">{{ $prescriptionCounts[$s->value] ?? 0 }}</dd></div>@endforeach
            </dl>
        </section>
        <section class="card-sage" aria-labelledby="lab-title">
            <h2 id="lab-title" class="text-xl">{{ __('التحاليل') }}</h2>
            <dl class="mt-3 space-y-1">
                @foreach ($labStatuses as $s)<div class="flex justify-between"><dt>{{ $s->label() }}</dt><dd class="font-semibold">{{ $labCounts[$s->value] ?? 0 }}</dd></div>@endforeach
            </dl>
        </section>
    </div>

    <h2 class="mb-3 text-xl">{{ __('طلبات الصيدلية والتوصيل') }}</h2>
    <form method="GET" class="mb-4 max-w-xs">
        <label for="status" class="label">{{ __('الحالة') }}</label>
        <select id="status" name="status" class="input" data-autosubmit>
            <option value="">{{ __('الكل') }}</option>
            @foreach (OrderStatus::cases() as $option)<option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>@endforeach
        </select>
    </form>

    @if ($orders->isEmpty())
        <x-empty :title="__('لا توجد طلبات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('طلبات الصيدلية والتوصيل') }}</caption>
                <thead><tr><th scope="col">{{ __('رقم') }}</th><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('الصيدلية') }}</th><th scope="col">{{ __('المدينة') }}</th><th scope="col">{{ __('الإجمالي') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col">{{ __('التاريخ') }}</th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->user->name }}</td>
                        <td>{{ $order->pharmacy?->name ?? '—' }}</td>
                        <td>{{ $order->delivery_city }}</td>
                        <td>{{ $order->total_halalas ? money($order->total_halalas) : '—' }}</td>
                        <td><x-badge :status="$order->status" /></td>
                        <td>{{ $order->created_at->translatedFormat('j M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    @endif
</x-layouts.app>
