<x-layouts.site :title="__('إعادة تعيين كلمة المرور')">
    <div class="section max-w-md">
        <h1 class="text-3xl">{{ __('إعادة تعيين كلمة المرور') }}</h1>

        <form method="POST" action="{{ route('password.update') }}" class="card mt-6 space-y-5" data-once novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-input name="email" type="email" :label="__('البريد الإلكتروني')" :value="$email" required autocomplete="email" dir="ltr" />
            <x-input name="password" type="password" :label="__('كلمة المرور الجديدة')" required autocomplete="new-password"
                     :hint="__('8 أحرف على الأقل، وتتضمن حروفًا وأرقامًا.')" />
            <x-input name="password_confirmation" type="password" :label="__('تأكيد كلمة المرور')" required autocomplete="new-password" />
            <button type="submit" class="btn btn-primary btn-block">{{ __('حفظ كلمة المرور') }}</button>
        </form>
    </div>
</x-layouts.site>
