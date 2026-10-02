@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-layouts.app :title="__('لوحة التحكم')">
    <x-page-header :title="__('لوحة التحكم')" :subtitle="__('أرقام تشغيلية فقط، دون محتوى طبي.')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat :label="__('العملاء')" :value="$snapshot['clients']" :href="route('admin.clients.index')" />
        <x-stat :label="__('اشتراكات فعّالة')" :value="$snapshot['activeSubscriptions']" :href="route('admin.subscriptions.index', ['status' => 'active'])" />
        <x-stat :label="__('تجديد تلقائي مفعّل')" :value="$snapshot['autoRenewing']" />
        <x-stat :label="__('تقييمات تنتظر مراجعة الطبيب')" :value="$snapshot['assessmentsWaiting']" />
        <x-stat :label="__('مواعيد اليوم')" :value="$snapshot['appointmentsToday']" :href="route('admin.appointments.index')" />
        <x-stat :label="__('استشارات فورية بانتظار طبيب')" :value="$snapshot['instantWaiting']" tone="peach" />
        <x-stat :label="__('طلبات تواصل مفتوحة')" :value="$snapshot['openContactRequests']" />
        <x-stat :label="__('طلبات دعم وشكاوى مفتوحة')" :value="$snapshot['openTickets']" :href="route('admin.tickets.index')" />
        <x-stat :label="__('طلبات أدوية قيد التنفيذ')" :value="$snapshot['ordersInProgress']" :href="route('admin.operations')" />
    </div>

    <section class="card mt-8" aria-labelledby="month-title">
        <h2 id="month-title" class="text-xl">{{ __('آخر 30 يومًا') }}</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-ink-soft">{{ __('عملاء جدد') }}</dt><dd class="text-2xl font-semibold">{{ $month['newClients'] }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('اشتراكات جديدة') }}</dt><dd class="text-2xl font-semibold">{{ $month['subscriptions']['new'] }}</dd></div>
            @if ($isAdmin)
                <div><dt class="text-sm text-ink-soft">{{ __('الإيرادات') }}</dt><dd class="text-2xl font-semibold">{{ money($month['payments']['revenue_halalas']) }}</dd></div>
            @endif
            <div><dt class="text-sm text-ink-soft">{{ __('استشارات مكتملة') }}</dt><dd class="text-2xl font-semibold">{{ $month['consultations']['completed'] }}</dd></div>
        </dl>
        @if ($isAdmin)<p class="mt-4"><a href="{{ route('admin.reports') }}">{{ __('التقارير التفصيلية') }}</a></p>@endif
    </section>
</x-layouts.app>
