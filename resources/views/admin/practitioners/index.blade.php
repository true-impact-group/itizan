@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-layouts.app :title="__('الأطباء والمختصون')">
    <x-page-header :title="__('الأطباء والمختصون')" :subtitle="__('لا يظهر المختص للعملاء ولا تُحجز معه استشارة إلا بعد توثيق ترخيصه.')">
        <x-slot:actions><a href="{{ route('admin.staff.create') }}" class="btn btn-primary">{{ __('إضافة موظف') }}</a></x-slot:actions>
    </x-page-header>

    <div class="table-wrap">
        <table class="table">
            <caption class="sr-only">{{ __('الأطباء والمختصون') }}</caption>
            <thead><tr><th scope="col">{{ __('الاسم') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('التخصص') }}</th><th scope="col">{{ __('الترخيص') }}</th><th scope="col">{{ __('التوثيق') }}</th><th scope="col">{{ __('أوقات التوفر') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
            <tbody>
            @foreach ($practitioners as $practitioner)
                <tr>
                    <td class="font-semibold">{{ $practitioner->user->name }}</td>
                    <td>{{ $practitioner->type->label() }}</td>
                    <td>{{ $practitioner->specialty_ar }}</td>
                    <td><bdi>{{ $practitioner->license_number ?? '—' }}</bdi></td>
                    <td>
                        @if ($practitioner->isVerified())<x-badge tone="success" :label="__('موثّق')" />
                        @elseif ($practitioner->license_verified_at)<x-badge tone="warning" :label="__('منتهي أو غير فعّال')" />
                        @else<x-badge tone="warning" :label="__('بانتظار التوثيق')" />@endif
                    </td>
                    <td>{{ $practitioner->availabilities_count }}</td>
                    <td><a href="{{ route('admin.practitioners.edit', $practitioner) }}">{{ __('تعديل') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $practitioners->links() }}
</x-layouts.app>
