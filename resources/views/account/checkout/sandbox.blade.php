<x-layouts.app :title="__('صفحة دفع تجريبية')">
    <x-page-header :title="__('صفحة دفع تجريبية')" />

    <div class="card max-w-xl space-y-4">
        <p class="alert alert-warning">{{ __('هذه بيئة تجريبية ولا يتم فيها خصم أي مبلغ حقيقي. في الإنتاج يظهر هنا موقع مزود الدفع.') }}</p>
        <p>{{ __('الباقة: :name', ['name' => $payment->subscription->package->name()]) }}</p>
        <p class="text-2xl font-bold">{{ money($payment->amount_halalas) }}</p>
        <p>{{ __('وسيلة الدفع: :method', ['method' => $payment->method->label()]) }}</p>

        <div class="space-y-3 rounded-lg border p-4" aria-labelledby="demo-card-title">
            <h2 id="demo-card-title" class="font-semibold">{{ __('بيانات بطاقة تجريبية للعرض') }}</h2>
            <p class="text-sm">{{ __('هذه بيانات وهمية للشرح فقط. لا تُرسل ولا تُحفظ، ولا تُدخل بطاقتك الحقيقية هنا.') }}</p>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt>{{ __('اسم حامل البطاقة') }}</dt><dd dir="ltr" class="text-start">DEMO CLIENT</dd>
                <dt>{{ __('رقم البطاقة') }}</dt><dd dir="ltr" class="text-start">4242 4242 4242 4242</dd>
                <dt>{{ __('تاريخ الانتهاء') }}</dt><dd dir="ltr" class="text-start">12 / 30</dd>
                <dt>{{ __('رمز الأمان (CVV)') }}</dt><dd dir="ltr" class="text-start">123</dd>
                <dt>{{ __('رمز التحقق (OTP)') }}</dt><dd dir="ltr" class="text-start">123456</dd>
            </dl>
            <p class="text-sm">{{ __('لتجربة الدفع المرفوض استخدم الرقم 4000 0000 0000 0002، ثم اضغط «محاكاة دفع فاشل».') }}</p>
        </div>

        <form method="POST" action="{{ route('checkout.sandbox.complete', $payment) }}" class="flex flex-wrap gap-3" data-once>
            @csrf
            <button type="submit" name="outcome" value="paid" class="btn btn-primary">{{ __('محاكاة دفع ناجح') }}</button>
            <button type="submit" name="outcome" value="failed" class="btn btn-danger">{{ __('محاكاة دفع فاشل') }}</button>
        </form>
    </div>
</x-layouts.app>
