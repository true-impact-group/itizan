@php use App\Enums\AppointmentStatus; @endphp
<x-layouts.app :title="__('المواعيد')">
    <x-page-header :title="__('المواعيد')" :subtitle="__('نظرة تشغيلية على الاستشارات. لا تظهر ملاحظات الجلسات.')" />

    <form method="GET" class="mb-4 max-w-xs">
        <label for="status" class="label">{{ __('الحالة') }}</label>
        <select id="status" name="status" class="input" data-autosubmit>
            <option value="">{{ __('الكل') }}</option>
            @foreach (AppointmentStatus::cases() as $option)<option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>@endforeach
        </select>
    </form>

    @if ($appointments->isEmpty())
        <x-empty :title="__('لا توجد مواعيد')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('المواعيد') }}</caption>
                <thead><tr><th scope="col">{{ __('الموعد') }}</th><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('الطبيب') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->scheduled_at->translatedFormat('D j M Y، h:i A') }}</td>
                        <td>{{ $appointment->user->name }}</td>
                        <td>{{ $appointment->practitioner?->displayName() ?? '—' }}</td>
                        <td>{{ $appointment->type->label() }} · {{ $appointment->mode->label() }}</td>
                        <td><x-badge :status="$appointment->status" /></td>
                        <td>
                            @if ($appointment->status->isActive())
                                <form method="POST" action="{{ route('admin.appointments.cancel', $appointment) }}" data-confirm="{{ __('إلغاء هذا الموعد؟') }}" data-once>
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('إلغاء') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $appointments->links() }}
    @endif
</x-layouts.app>
