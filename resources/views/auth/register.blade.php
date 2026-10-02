<x-layouts.site :title="__('إنشاء حساب')">
    <div class="section max-w-2xl">
        <h1 class="text-3xl">{{ __('أنشئ حسابك في اتزان') }}</h1>
        <p class="mt-2 text-lg text-ink-soft">{{ __('خطوتك الأولى تبدأ بمعلومات بسيطة لحجز استشارتك. أنت لا تلتزم بأي اشتراك.') }}</p>

        <form method="POST" action="{{ route('register') }}" class="card mt-6 space-y-5" data-once novalidate>
            @csrf
            <x-input name="name" :label="__('الاسم الكامل')" required autocomplete="name" />
            <x-input name="phone" type="tel" :label="__('رقم الجوال')" required autocomplete="tel" inputmode="tel" dir="ltr" placeholder="05xxxxxxxx"
                     :hint="__('نستخدمه لحجز استشارتك وتسجيل دخولك. مثال: 0551234567')" />
            <x-input name="email" type="email" :label="__('البريد الإلكتروني (اختياري)')" autocomplete="email" dir="ltr"
                     :hint="__('لاستعادة كلمة المرور واستلام الإيصالات.')" />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="password" type="password" :label="__('كلمة المرور')" required autocomplete="new-password"
                         :hint="__('8 أحرف على الأقل، وتتضمن حروفًا وأرقامًا.')" />
                <x-input name="password_confirmation" type="password" :label="__('تأكيد كلمة المرور')" required autocomplete="new-password" />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-select name="gender" :label="__('الجنس')" :options="\App\Enums\Gender::options()" :placeholder="__('اختر')" />
                <x-input name="birth_date" type="date" :label="__('تاريخ الميلاد')" autocomplete="bday" />
                <x-select name="city" :label="__('المدينة')" :options="array_combine(config('itizan.cities'), config('itizan.cities'))" :placeholder="__('اختر')" />
            </div>

            <fieldset class="space-y-3 rounded-xl bg-ivory p-4">
                <legend class="px-1 font-semibold">{{ __('الموافقات') }}</legend>
                <x-checkbox name="terms" value="1">
                    <x-slot:label>
                        {{ __('أوافق على') }}
                        <a href="{{ route('policies.show', 'terms') }}" target="_blank" rel="noopener">{{ __('الشروط والأحكام') }}</a>
                        {{ __('و') }}
                        <a href="{{ route('policies.show', 'privacy') }}" target="_blank" rel="noopener">{{ __('سياسة الخصوصية') }}</a>
                    </x-slot:label>
                </x-checkbox>
                <x-checkbox name="marketing_consent" :label="__('أوافق على استقبال عروض ورسائل تسويقية (اختياري)')"
                            :hint="__('لا نستخدم بياناتك الصحية لأغراض التسويق. يمكنك تغيير اختيارك في أي وقت.')" />
            </fieldset>

            <button type="submit" class="btn btn-primary btn-block">{{ __('إنشاء الحساب') }}</button>
        </form>

        <p class="mt-5 text-center">{{ __('لديك حساب؟') }} <a href="{{ route('login') }}">{{ __('تسجيل الدخول') }}</a></p>
    </div>
</x-layouts.site>
