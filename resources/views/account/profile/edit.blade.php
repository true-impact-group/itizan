@php use App\Enums\Gender; @endphp
<x-layouts.app :title="__('ملفي الشخصي')">
    <x-page-header :title="__('ملفي الشخصي')" />

    <form method="POST" action="{{ route('account.profile.update') }}" class="card max-w-2xl space-y-5" data-once novalidate>
        @csrf @method('PUT')
        <x-input name="name" :label="__('الاسم الكامل')" :value="$user->name" required />
        <div>
            <span class="label">{{ __('رقم الجوال') }}</span>
            <p class="input bg-ivory" dir="ltr">{{ $user->phone }}</p>
            <span class="hint">{{ __('لتغيير رقم الجوال تواصل مع الدعم.') }}</span>
        </div>
        <x-input name="email" type="email" :label="__('البريد الإلكتروني')" :value="$user->email" dir="ltr" />
        <div class="grid gap-5 sm:grid-cols-3">
            <x-select name="gender" :label="__('الجنس')" :options="Gender::options()" :value="$user->gender?->value" :placeholder="__('اختر')" />
            <x-input name="birth_date" type="date" :label="__('تاريخ الميلاد')" :value="$user->birth_date?->toDateString()" />
            <x-select name="city" :label="__('المدينة')" :options="array_combine($cities, $cities)" :value="$user->city" :placeholder="__('اختر')" />
        </div>
        <x-select name="locale" :label="__('لغة الموقع')" :options="['ar' => 'العربية', 'en' => 'English']" :value="$user->locale" required />
        <x-checkbox name="marketing_consent" :label="__('أوافق على استقبال عروض ورسائل تسويقية')" :checked="$marketingConsent"
                    :hint="__('لا نستخدم بياناتك الصحية لأغراض التسويق. يمكنك تغيير اختيارك في أي وقت.')" />
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>

    <form method="POST" action="{{ route('account.profile.password') }}" class="card mt-6 max-w-2xl space-y-5" data-once novalidate>
        @csrf @method('PUT')
        <h2 class="text-xl">{{ __('تغيير كلمة المرور') }}</h2>
        <x-input name="current_password" type="password" :label="__('كلمة المرور الحالية')" required autocomplete="current-password" />
        <x-input name="password" type="password" :label="__('كلمة المرور الجديدة')" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" :label="__('تأكيد كلمة المرور')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-secondary">{{ __('تغيير كلمة المرور') }}</button>
    </form>
</x-layouts.app>
