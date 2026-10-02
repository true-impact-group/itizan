<x-layouts.app :title="__('المواعيد')">
    <x-page-header :title="__('المواعيد')" />

    <nav class="mb-4 flex gap-2" aria-label="{{ __('تصفية') }}">
        <a href="{{ route('practitioner.appointments.index') }}" class="btn btn-sm {{ $tab === 'upcoming' ? 'btn-primary' : 'btn-secondary' }}" @if ($tab === 'upcoming') aria-current="page" @endif>{{ __('القادمة') }}</a>
        <a href="{{ route('practitioner.appointments.index', ['tab' => 'past']) }}" class="btn btn-sm {{ $tab === 'past' ? 'btn-primary' : 'btn-secondary' }}" @if ($tab === 'past') aria-current="page" @endif>{{ __('السابقة') }}</a>
    </nav>

    @if ($appointments->isEmpty())
        <x-empty :title="__('لا توجد مواعيد')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('المواعيد') }}</caption>
                <thead><tr><th scope="col">{{ __('الموعد') }}</th><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->scheduled_at->translatedFormat('D j M Y، h:i A') }}</td>
                        <td>{{ $appointment->user->name }}</td>
                        <td>{{ $appointment->type->label() }} · {{ $appointment->mode->label() }}</td>
                        <td><x-badge :status="$appointment->status" /></td>
                        <td><a href="{{ route('practitioner.appointments.show', $appointment) }}">{{ __('فتح') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $appointments->links() }}
    @endif
</x-layouts.app>
