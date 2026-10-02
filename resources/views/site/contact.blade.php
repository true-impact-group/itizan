<x-layouts.site :title="__('تواصل معنا')">
    <div class="section max-w-4xl">
        <h1 class="text-4xl">{{ __('تواصل معنا') }}</h1>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <section class="card" aria-labelledby="wa-title">
                <h2 id="wa-title" class="text-xl">{{ __('واتساب') }}</h2>
                <p class="mt-2">{{ __('للاستفسارات والدعم. تظهر الرسالة مكتوبة في المحادثة، وتضغط «إرسال».') }}</p>
                <p class="mt-2 rounded-xl bg-ivory p-3 text-ink-soft" dir="rtl">{{ config('itizan.whatsapp.message') }}</p>
                <a href="{{ \App\Support\Whatsapp::url() }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary mt-4">{{ __('فتح محادثة واتساب') }}</a>
                <p class="mt-3 text-sm text-ink-soft">{{ __('لا ترسل معلوماتك الصحية الحساسة عبر واتساب. أرسلها من حسابك في المنصة.') }}</p>
            </section>

            <section class="card" aria-labelledby="mail-title">
                <h2 id="mail-title" class="text-xl">{{ __('البريد الإلكتروني') }}</h2>
                <p class="mt-2"><a href="mailto:{{ config('itizan.support_email') }}">{{ config('itizan.support_email') }}</a></p>
                <h2 class="mt-6 text-xl">{{ __('الشكاوى والملاحظات') }}</h2>
                <p class="mt-2">{{ __('إن كنت عميلًا، يمكنك رفع شكوى أو طلب دعم من حسابك ومتابعته.') }}</p>
                <a href="{{ route('policies.show', 'complaints') }}" class="mt-2 inline-block">{{ __('سياسة الشكاوى') }}</a>
            </section>
        </div>

        <x-emergency class="mt-8" />
    </div>
</x-layouts.site>
