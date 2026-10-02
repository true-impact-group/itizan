@php
    use App\Enums\Role;
    $practitioner = $member?->practitioner;
    $roles = collect(Role::cases())->reject(fn ($r) => $r === Role::Client)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all();
@endphp
<x-layouts.app :title="$member ? __('تعديل موظف') : __('إضافة موظف')">
    <p class="mb-2"><a href="{{ route('admin.staff.index') }}">← {{ __('الموظفون والصلاحيات') }}</a></p>
    <x-page-header :title="$member ? $member->name : __('إضافة موظف')" />

    <form method="POST" action="{{ $member ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="card max-w-3xl space-y-5" data-once novalidate>
        @csrf
        @if ($member) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="name" :label="__('الاسم')" :value="$member?->name" required />
            <x-input name="phone" type="tel" :label="__('الجوال')" :value="$member?->phone" required dir="ltr" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="email" type="email" :label="__('البريد الإلكتروني')" :value="$member?->email" dir="ltr" />
            <x-input name="password" type="password" :label="$member ? __('كلمة مرور جديدة (اتركها فارغة لعدم التغيير)') : __('كلمة المرور')" :required="! $member" autocomplete="new-password" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-select name="role" :label="__('الدور')" :options="$roles" :value="$member?->role?->value" :placeholder="__('اختر')" required />
            <x-select name="pharmacy_id" :label="__('الصيدلية (لموظفي الصيدلية)')" :options="$pharmacies->pluck('name', 'id')->all()" :value="$member?->pharmacy_id" :placeholder="__('بدون')" />
        </div>

        <fieldset class="space-y-5 rounded-xl border border-line p-4">
            <legend class="px-2 font-semibold">{{ __('بيانات الطبيب / أخصائي التغذية') }}</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="name_en" :label="__('الاسم بالإنجليزية')" :value="$practitioner?->name_en" dir="ltr" />
                <x-input name="license_number" :label="__('رقم الترخيص')" :value="$practitioner?->license_number" dir="ltr" />
                <x-input name="specialty_ar" :label="__('التخصص (عربي)')" :value="$practitioner?->specialty_ar" />
                <x-input name="specialty_en" :label="__('التخصص (إنجليزي)')" :value="$practitioner?->specialty_en" dir="ltr" />
            </div>
            <x-checkbox name="accepts_instant" :label="__('يستقبل الاستشارات الفورية (للأطباء)')" :checked="$practitioner?->accepts_instant" />
            <p class="hint">{{ __('يُوثَّق الترخيص من صفحة «الأطباء والمختصون» قبل ظهور المختص للعملاء.') }}</p>
        </fieldset>

        <x-checkbox name="is_active" :label="__('الحساب مفعّل')" :checked="$member ? $member->is_active : true" />
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
