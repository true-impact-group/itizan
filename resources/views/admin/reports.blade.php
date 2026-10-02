@php
    $paymentMethods = \App\Enums\PaymentMethod::options();
@endphp
<x-layouts.app :title="__('التقارير')">
    <x-page-header :title="__('التقارير')" :subtitle="__('الاشتراكات والتجديد والإلغاء والمدفوعات والاستشارات والتوصيل والشكاوى.')" />

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-4">
        <x-input name="from" type="date" :label="__('من')" :value="$from->toDateString()" />
        <x-input name="to" type="date" :label="__('إلى')" :value="$to->toDateString()" />
        <button type="submit" class="btn btn-primary">{{ __('عرض') }}</button>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card" aria-labelledby="r-sub">
            <h2 id="r-sub" class="text-xl">{{ __('الاشتراكات') }}</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between"><dt>{{ __('عملاء جدد') }}</dt><dd class="font-semibold">{{ $report['newClients'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('اشتراكات جديدة') }}</dt><dd class="font-semibold">{{ $report['subscriptions']['new'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('منها تجديدات') }}</dt><dd class="font-semibold">{{ $report['subscriptions']['renewals'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('اشتراكات ملغاة') }}</dt><dd class="font-semibold">{{ $report['subscriptions']['cancelled'] }}</dd></div>
            </dl>
            @foreach ($report['subscriptions']['byPackage'] as $name => $total)
                @if ($loop->first)<h3 class="mt-4 text-base">{{ __('حسب الباقة') }}</h3>@endif
                <p class="flex justify-between"><span>{{ $name }}</span><span>{{ $total }}</span></p>
            @endforeach
        </section>

        <section class="card" aria-labelledby="r-pay">
            <h2 id="r-pay" class="text-xl">{{ __('المدفوعات') }}</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between"><dt>{{ __('عدد المدفوعات') }}</dt><dd class="font-semibold">{{ $report['payments']['count'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('إجمالي المحصّل') }}</dt><dd class="font-semibold">{{ money($report['payments']['revenue_halalas']) }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('منها ضريبة القيمة المضافة') }}</dt><dd class="font-semibold">{{ money($report['payments']['vat_halalas']) }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('المستردات') }}</dt><dd class="font-semibold">{{ $report['payments']['refund_count'] }} ({{ money($report['payments']['refund_halalas']) }})</dd></div>
            </dl>
            @foreach ($report['payments']['byMethod'] as $method => $total)
                @if ($loop->first)<h3 class="mt-4 text-base">{{ __('حسب وسيلة الدفع') }}</h3>@endif
                <p class="flex justify-between"><span>{{ $paymentMethods[$method] ?? $method }}</span><span>{{ $total }}</span></p>
            @endforeach
        </section>

        <section class="card" aria-labelledby="r-con">
            <h2 id="r-con" class="text-xl">{{ __('الاستشارات') }}</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between"><dt>{{ __('استشارات مجانية') }}</dt><dd class="font-semibold">{{ $report['consultations']['free'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('استشارات متابعة') }}</dt><dd class="font-semibold">{{ $report['consultations']['followUp'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('مكتملة') }}</dt><dd class="font-semibold">{{ $report['consultations']['completed'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('لم يحضر العميل') }}</dt><dd class="font-semibold">{{ $report['consultations']['noShow'] }}</dd></div>
            </dl>
        </section>

        <section class="card" aria-labelledby="r-del">
            <h2 id="r-del" class="text-xl">{{ __('التوصيل والشكاوى') }}</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between"><dt>{{ __('طلبات مُسلَّمة') }}</dt><dd class="font-semibold">{{ $report['delivery']['delivered'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('طلبات مرفوضة') }}</dt><dd class="font-semibold">{{ $report['delivery']['rejected'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('متوسط زمن التوصيل بعد الموافقة (ساعة)') }}</dt><dd class="font-semibold">{{ $report['delivery']['avgHours'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('شكاوى جديدة') }}</dt><dd class="font-semibold">{{ $report['complaints']['opened'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('شكاوى تم حلها') }}</dt><dd class="font-semibold">{{ $report['complaints']['resolved'] }}</dd></div>
                <div class="flex justify-between"><dt>{{ __('طلبات دعم') }}</dt><dd class="font-semibold">{{ $report['complaints']['support'] }}</dd></div>
            </dl>
        </section>
    </div>
</x-layouts.app>
