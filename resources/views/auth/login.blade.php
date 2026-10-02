<x-layouts.site :title="__('تسجيل الدخول')">
    <div class="section max-w-md">
        <h1 class="text-3xl">{{ __('تسجيل الدخول') }}</h1>
        <p class="mt-2 text-ink-soft">{{ __('أهلًا بعودتك. ادخل برقم جوالك أو بريدك الإلكتروني.') }}</p>

        <form method="POST" action="{{ route('login') }}" class="card mt-6 space-y-5" data-once novalidate>
            @csrf
            <x-input name="login" :label="__('رقم الجوال أو البريد الإلكتروني')" required autocomplete="username" dir="ltr" autofocus />
            <x-input name="password" type="password" :label="__('كلمة المرور')" required autocomplete="current-password" />
            <x-checkbox name="remember" :label="__('تذكرني على هذا الجهاز')" />
            <button type="submit" class="btn btn-primary btn-block">{{ __('دخول') }}</button>
        </form>

        <p class="mt-5 text-center"><a href="{{ route('password.request') }}">{{ __('نسيت كلمة المرور؟') }}</a></p>
        <p class="mt-2 text-center">{{ __('ليس لديك حساب؟') }} <a href="{{ route('register') }}">{{ __('إنشاء حساب') }}</a></p>
    </div>
</x-layouts.site>
