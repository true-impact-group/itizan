@php use App\Enums\Role; @endphp
<x-layouts.app :title="__('الموظفون والصلاحيات')">
    <x-page-header :title="__('الموظفون والصلاحيات')" :subtitle="__('الدور يحدد ما يراه الموظف. الطبيب وأخصائي التغذية وفريق الرعاية فقط يصلون إلى البيانات الطبية.')">
        <x-slot:actions><a href="{{ route('admin.staff.create') }}" class="btn btn-primary">{{ __('إضافة موظف') }}</a></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 max-w-xs">
        <label for="role" class="label">{{ __('الدور') }}</label>
        <select id="role" name="role" class="input" data-autosubmit>
            <option value="">{{ __('الكل') }}</option>
            @foreach (Role::cases() as $option)@continue($option === Role::Client)<option value="{{ $option->value }}" @selected($role === $option)>{{ $option->label() }}</option>@endforeach
        </select>
    </form>

    <div class="table-wrap">
        <table class="table">
            <caption class="sr-only">{{ __('الموظفون') }}</caption>
            <thead><tr><th scope="col">{{ __('الاسم') }}</th><th scope="col">{{ __('الدور') }}</th><th scope="col">{{ __('الجوال') }}</th><th scope="col">{{ __('الصيدلية') }}</th><th scope="col">{{ __('الحساب') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
            <tbody>
            @foreach ($staff as $member)
                <tr>
                    <td class="font-semibold">{{ $member->name }}</td>
                    <td>{{ $member->role->label() }}</td>
                    <td><bdi>{{ $member->phone }}</bdi></td>
                    <td>{{ $member->pharmacy?->name ?? '—' }}</td>
                    <td>@if ($member->is_active)<x-badge tone="success" :label="__('مفعّل')" />@else<x-badge tone="danger" :label="__('موقوف')" />@endif</td>
                    <td><a href="{{ route('admin.staff.edit', $member) }}">{{ __('تعديل') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $staff->links() }}
</x-layouts.app>
