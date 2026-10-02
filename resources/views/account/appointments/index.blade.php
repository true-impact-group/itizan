<x-layouts.app :title="__('المواعيد والاستشارات')">
    <x-page-header :title="__('المواعيد والاستشارات')">
        <x-slot:actions>
            <a href="{{ route('account.appointments.create') }}" class="btn btn-primary">{{ __('حجز استشارة') }}</a>
        </x-slot:actions>
    </x-page-header>

    @if ($appointments->isEmpty())
        <x-empty :title="__('لا توجد مواعيد بعد')" :text="__('ابدأ باستشارتك المجانية وتعرّف على الخطوة المناسبة لك.')">
            <a href="{{ route('account.appointments.create', ['type' => 'free_intro']) }}" class="btn btn-primary">{{ __('احجز الاستشارة المجانية') }}</a>
        </x-empty>
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('المواعيد والاستشارات') }}</caption>
                <thead><tr><th scope="col">{{ __('الموعد') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('الطبيب') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->is_instant && ! $appointment->practitioner_id ? __('فورية') : $appointment->scheduled_at->translatedFormat('D j M Y، h:i A') }}</td>
                        <td>{{ $appointment->type->label() }} · {{ $appointment->mode->label() }}</td>
                        <td>{{ $appointment->practitioner?->displayName() ?? __('بانتظار التعيين') }}</td>
                        <td><x-badge :status="$appointment->status" /></td>
                        <td><a href="{{ route('account.appointments.show', $appointment) }}">{{ __('التفاصيل') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $appointments->links() }}
    @endif
</x-layouts.app>
