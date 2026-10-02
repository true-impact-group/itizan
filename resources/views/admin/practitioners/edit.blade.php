@php $verified = $practitioner->isVerified(); @endphp
<x-layouts.app :title="__('بيانات المختص')">
    <p class="mb-2"><a href="{{ route('admin.practitioners.index') }}">← {{ __('الأطباء والمختصون') }}</a></p>
    <x-page-header :title="$practitioner->user->name" :subtitle="$practitioner->type->label()" />

    <section class="card-sage mb-6" aria-labelledby="verify-title">
        <h2 id="verify-title" class="text-xl">{{ __('توثيق الترخيص') }}</h2>
        <p class="mt-2">
            @if ($verified)
                <x-badge tone="success" :label="__('موثّق')" />
                {{ __('ظاهر للعملاء ومتاح للحجز.') }}
                @if ($practitioner->license_expires_on) {{ __('ينتهي الترخيص في :date', ['date' => $practitioner->license_expires_on->translatedFormat('j F Y')]) }}@endif
            @else
                <x-badge tone="warning" :label="__('غير موثّق')" /> {{ __('تحقق من الرقم لدى الجهة المرخِّصة ثم وثّق.') }}
            @endif
        </p>
        <div class="mt-4 flex flex-wrap items-end gap-3">
            <form method="POST" action="{{ route('admin.practitioners.verify', $practitioner) }}" class="flex flex-wrap items-end gap-3" data-once>
                @csrf
                <x-input name="license_expires_on" type="date" :label="__('تاريخ انتهاء الترخيص')" :value="$practitioner->license_expires_on?->toDateString()" />
                <button type="submit" class="btn btn-primary">{{ $verified ? __('تحديث التوثيق') : __('توثيق الترخيص') }}</button>
            </form>
            @if ($practitioner->license_verified_at)
                <form method="POST" action="{{ route('admin.practitioners.revoke', $practitioner) }}" data-confirm="{{ __('سحب التوثيق وإخفاء المختص عن العملاء؟') }}" data-once>
                    @csrf
                    <button type="submit" class="btn btn-danger">{{ __('سحب التوثيق') }}</button>
                </form>
            @endif
        </div>
    </section>

    <form method="POST" action="{{ route('admin.practitioners.update', $practitioner) }}" class="card max-w-3xl space-y-5" data-once novalidate>
        @csrf @method('PUT')
        <x-input name="name_en" :label="__('الاسم بالإنجليزية')" :value="$practitioner->name_en" dir="ltr" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="specialty_ar" :label="__('التخصص (عربي)')" :value="$practitioner->specialty_ar" required />
            <x-input name="specialty_en" :label="__('التخصص (إنجليزي)')" :value="$practitioner->specialty_en" dir="ltr" />
        </div>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-input name="license_number" :label="__('رقم الترخيص')" :value="$practitioner->license_number" dir="ltr" :hint="__('تغيير الرقم يُلغي التوثيق السابق.')" />
            <x-input name="license_authority" :label="__('الجهة المرخِّصة')" :value="$practitioner->license_authority" required />
            <x-input name="license_expires_on" type="date" :label="__('انتهاء الترخيص')" :value="$practitioner->license_expires_on?->toDateString()" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-textarea name="bio_ar" :label="__('نبذة (عربي)')" rows="3" :value="$practitioner->bio_ar" />
            <x-textarea name="bio_en" :label="__('نبذة (إنجليزي)')" rows="3" :value="$practitioner->bio_en" dir="ltr" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="years_experience" type="number" min="0" max="70" :label="__('سنوات الخبرة')" :value="$practitioner->years_experience" />
            <x-input name="sort_order" type="number" min="0" :label="__('الترتيب')" :value="$practitioner->sort_order" />
        </div>
        <x-checkbox name="is_active" :label="__('فعّال')" :checked="$practitioner->is_active" />
        @if ($practitioner->type === \App\Enums\PractitionerType::Doctor)
            <x-checkbox name="accepts_instant" :label="__('يستقبل الاستشارات الفورية')" :checked="$practitioner->accepts_instant" />
        @endif
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
