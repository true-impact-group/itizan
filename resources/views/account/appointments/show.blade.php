<x-layouts.app :title="__('تفاصيل الموعد')">
    <p class="mb-2"><a href="{{ route('account.appointments.index') }}">← {{ __('كل المواعيد') }}</a></p>
    <x-page-header :title="$appointment->type->label()" />

    <div class="card space-y-4">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-ink-soft">{{ __('الموعد') }}</dt>
                <dd class="text-lg font-semibold">
                    {{ $appointment->is_instant && ! $appointment->practitioner_id ? __('فورية') : $appointment->scheduled_at->translatedFormat('l j F Y، h:i A') }}
                </dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الحالة') }}</dt><dd><x-badge :status="$appointment->status" /></dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('طريقة الاستشارة') }}</dt><dd>{{ $appointment->mode->label() }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الطبيب') }}</dt>
                <dd>
                    @if ($appointment->practitioner)
                        {{ $appointment->practitioner->displayName() }} — {{ $appointment->practitioner->specialty() }}
                        <span class="block text-sm text-ink-soft">{{ __('رقم الترخيص') }}: <bdi>{{ $appointment->practitioner->license_number }}</bdi></span>
                    @else
                        {{ __('بانتظار أول طبيب متاح') }}
                    @endif
                </dd></div>
        </dl>

        @if ($appointment->isJoinable())
            <a href="{{ $appointment->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">{{ __('الدخول إلى الاستشارة (FTMS)') }}</a>
        @elseif ($appointment->status === \App\Enums\AppointmentStatus::Confirmed)
            <p class="alert alert-info">{{ __('يفتح رابط الدخول قبل الموعد بعشر دقائق. تُقدَّم الاستشارة عبر منصة الطب الاتصالي FTMS.') }}</p>
        @elseif ($appointment->status === \App\Enums\AppointmentStatus::Requested)
            <p class="alert alert-info">{{ __('طلبك قيد الانتظار. سيتواصل معك أول طبيب متاح، وسنرسل لك تنبيهًا.') }}</p>
        @endif

        @if ($appointment->isCancellable())
            <form method="POST" action="{{ route('account.appointments.cancel', $appointment) }}" data-confirm="{{ __('هل تريد إلغاء هذا الموعد؟') }}" data-once>
                @csrf
                <button type="submit" class="btn btn-danger">{{ __('إلغاء الموعد') }}</button>
            </form>
        @endif
    </div>

    <x-emergency class="mt-6" />
</x-layouts.app>
