<x-layouts.site :title="__('استعادة كلمة المرور')">
    <div class="section max-w-md">
        <h1 class="text-3xl">{{ __('استعادة كلمة المرور') }}</h1>
        <p class="mt-2 text-ink-soft">{{ __('أدخل بريدك الإلكتروني المسجل وسنرسل لك رابطًا لإعادة التعيين.') }}</p>

        <form method="POST" action="{{ route('password.email') }}" class="card mt-6 space-y-5" data-once novalidate>
            @csrf
            <x-input name="email" type="email" :label="__('البريد الإلكتروني')" required autocomplete="email" dir="ltr" />
            <button type="submit" class="btn btn-primary btn-block">{{ __('إرسال الرابط') }}</button>
        </form>

        <p class="card-sage mt-5">
            {{ __('لم تسجل بريدًا إلكترونيًا؟ تواصل معنا عبر واتساب لمساعدتك في استعادة حسابك.') }}
            <a href="{{ \App\Support\Whatsapp::url() }}" target="_blank" rel="noopener noreferrer">{{ __('واتساب') }}</a>
        </p>
        <p class="mt-4 text-center"><a href="{{ route('login') }}">{{ __('العودة لتسجيل الدخول') }}</a></p>
    </div>
</x-layouts.site>
