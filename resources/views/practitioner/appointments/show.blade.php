@php use App\Enums\AppointmentStatus; @endphp
<x-layouts.app :title="__('تفاصيل الموعد')">
    <p class="mb-2"><a href="{{ route('practitioner.appointments.index') }}">← {{ __('المواعيد') }}</a></p>
    <x-page-header :title="$appointment->type->label()"><x-slot:actions><x-badge :status="$appointment->status" /></x-slot:actions></x-page-header>

    <div class="card space-y-4">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-ink-soft">{{ __('العميل') }}</dt><dd class="text-lg font-semibold">{{ $appointment->user->name }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الموعد') }}</dt><dd class="text-lg font-semibold">{{ $appointment->scheduled_at->translatedFormat('l j F Y، h:i A') }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('طريقة الاستشارة') }}</dt><dd>{{ $appointment->mode->label() }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('ما يود العميل مناقشته') }}</dt><dd class="whitespace-pre-line">{{ $appointment->client_notes ?: '—' }}</dd></div>
        </dl>

        @if ($appointment->status === AppointmentStatus::Confirmed && $appointment->meeting_url)
            <a href="{{ $appointment->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">{{ __('بدء الجلسة عبر FTMS') }}</a>
        @endif

        <a href="{{ route('practitioner.clients.show', $appointment->user_id) }}" class="btn btn-secondary">{{ __('فتح الملف السريري للعميل') }}</a>
    </div>

    @can('complete', $appointment)
        <form method="POST" action="{{ route('practitioner.appointments.complete', $appointment) }}" class="card mt-6 space-y-4" data-once novalidate>
            @csrf
            <h2 class="text-xl">{{ __('إنهاء الموعد') }}</h2>
            <x-select name="outcome" :label="__('النتيجة')" :options="[AppointmentStatus::Completed->value => AppointmentStatus::Completed->label(), AppointmentStatus::NoShow->value => AppointmentStatus::NoShow->label()]" required />
            <x-textarea name="notes" :label="__('ملاحظات الجلسة (داخلية)')" rows="4" :value="$appointment->practitioner_notes" />
            <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
        </form>
    @elseif ($appointment->practitioner_notes)
        <section class="card mt-6"><h2 class="text-xl">{{ __('ملاحظات الجلسة') }}</h2><p class="mt-2 whitespace-pre-line">{{ $appointment->practitioner_notes }}</p></section>
    @endcan
</x-layouts.app>
