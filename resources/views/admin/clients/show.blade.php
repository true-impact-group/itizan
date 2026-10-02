@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-layouts.app :title="$client->name">
    <p class="mb-2"><a href="{{ route('admin.clients.index') }}">← {{ __('العملاء') }}</a></p>
    <x-page-header :title="$client->name">
        <x-slot:actions>
            @if ($isAdmin)
                <form method="POST" action="{{ route('admin.clients.toggle-active', $client) }}" data-confirm="{{ $client->is_active ? __('إيقاف هذا الحساب؟') : __('تفعيل هذا الحساب؟') }}" data-once>
                    @csrf
                    <button type="submit" class="btn {{ $client->is_active ? 'btn-danger' : 'btn-primary' }} btn-sm">{{ $client->is_active ? __('إيقاف الحساب') : __('تفعيل الحساب') }}</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <p class="alert alert-info mb-6">{{ __('هذه الصفحة لإدارة الحساب والدعم. لا تتضمن أي معلومات طبية.') }}</p>

    <section class="card mb-6" aria-labelledby="acc-title">
        <h2 id="acc-title" class="text-xl">{{ __('بيانات الحساب') }}</h2>
        <dl class="mt-3 grid gap-4 sm:grid-cols-3">
            <div><dt class="text-sm text-ink-soft">{{ __('الجوال') }}</dt><dd dir="ltr" class="text-start">{{ $client->phone }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('البريد الإلكتروني') }}</dt><dd>{{ $client->email ?? '—' }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('المدينة') }}</dt><dd>{{ $client->city ?? '—' }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('انضم في') }}</dt><dd>{{ $client->created_at->translatedFormat('j F Y') }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('آخر دخول') }}</dt><dd>{{ $client->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الحساب') }}</dt><dd>@if ($client->is_active)<x-badge tone="success" :label="__('مفعّل')" />@else<x-badge tone="danger" :label="__('موقوف')" />@endif</dd></div>
        </dl>
    </section>

    <section class="card mb-6" aria-labelledby="pd-title">
        <h2 id="pd-title" class="text-xl">{{ __('الطبيب المتابع') }}</h2>
        <p class="mt-2">{{ $client->primaryPractitioner?->displayName() ?? __('لم يُعيَّن بعد (يُعيَّن تلقائيًا عند اعتماد تقييمه)') }}</p>
        @if ($isAdmin)
            <form method="POST" action="{{ route('admin.clients.primary-doctor', $client) }}" class="mt-3 flex flex-wrap items-end gap-3" data-once>
                @csrf @method('PUT')
                <div class="min-w-64"><x-select name="practitioner_id" :label="__('تغيير الطبيب المتابع')" :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all()" :value="$client->primary_practitioner_id" :placeholder="__('بدون')" /></div>
                <button type="submit" class="btn btn-secondary">{{ __('حفظ') }}</button>
            </form>
        @endif
    </section>

    <section class="card mb-6" aria-labelledby="subs-title">
        <h2 id="subs-title" class="text-xl">{{ __('الاشتراكات') }}</h2>
        @forelse ($subscriptions as $subscription)
            <p class="mt-2">{{ $subscription->package->name() }} · <x-badge :status="$subscription->status" /> · {{ $subscription->starts_at->translatedFormat('j F Y') }} — {{ $subscription->ends_at->translatedFormat('j F Y') }} · {{ __('متابعة: :used/:total', ['used' => $subscription->followups_used, 'total' => $subscription->followups_included]) }}</p>
        @empty
            <p class="mt-2 text-ink-soft">{{ __('لا توجد اشتراكات.') }}</p>
        @endforelse
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card" aria-labelledby="pay-title">
            <h2 id="pay-title" class="text-xl">{{ __('المدفوعات') }}</h2>
            @forelse ($payments as $payment)
                <p class="mt-2">{{ money($payment->amount_halalas) }} · <x-badge :status="$payment->status" /></p>
            @empty
                <p class="mt-2 text-ink-soft">—</p>
            @endforelse
        </section>
        <section class="card" aria-labelledby="app-title">
            <h2 id="app-title" class="text-xl">{{ __('المواعيد') }}</h2>
            @forelse ($appointments as $appointment)
                <p class="mt-2">{{ $appointment->scheduled_at->translatedFormat('j M Y') }} · {{ $appointment->type->label() }} · <x-badge :status="$appointment->status" /></p>
            @empty
                <p class="mt-2 text-ink-soft">—</p>
            @endforelse
        </section>
        <section class="card" aria-labelledby="tick-title">
            <h2 id="tick-title" class="text-xl">{{ __('الدعم والشكاوى') }}</h2>
            @forelse ($tickets as $ticket)
                <p class="mt-2"><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->subject }}</a> <x-badge :status="$ticket->status" /></p>
            @empty
                <p class="mt-2 text-ink-soft">—</p>
            @endforelse
        </section>
    </div>
</x-layouts.app>
