<x-layouts.app :title="__('الصيدليات')">
    <x-page-header :title="__('الصيدليات')">
        <x-slot:actions><a href="{{ route('admin.pharmacies.create') }}" class="btn btn-primary">{{ __('إضافة صيدلية') }}</a></x-slot:actions>
    </x-page-header>

    @if ($pharmacies->isEmpty())
        <x-empty :title="__('لا توجد صيدليات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('الصيدليات') }}</caption>
                <thead><tr><th scope="col">{{ __('الاسم') }}</th><th scope="col">{{ __('رقم الترخيص') }}</th><th scope="col">{{ __('المدينة') }}</th><th scope="col">{{ __('الموظفون') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($pharmacies as $pharmacy)
                    <tr>
                        <td class="font-semibold">{{ $pharmacy->name }}</td>
                        <td><bdi>{{ $pharmacy->license_number }}</bdi></td>
                        <td>{{ $pharmacy->city }}</td>
                        <td>{{ $pharmacy->staff_count }}</td>
                        <td><x-badge :tone="$pharmacy->is_active ? 'success' : 'neutral'" :label="$pharmacy->is_active ? __('فعّالة') : __('غير فعّالة')" /></td>
                        <td><a href="{{ route('admin.pharmacies.edit', $pharmacy) }}">{{ __('تعديل') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $pharmacies->links() }}
    @endif
</x-layouts.app>
