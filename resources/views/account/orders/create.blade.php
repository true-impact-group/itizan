@php
    $freeList = implode('، ', $freeCities);
    $selectedProductId = $prescriptions->firstWhere('id', $selectedPrescription)?->product_id;
@endphp
<x-layouts.app :title="__('طلب دواء أو مكمل')">
    <x-page-header :title="__('طلب دواء أو مكمل')" :subtitle="__('ترسل طلبك إلى صيدلية مرخّصة، وتراجع التوفر والسعر وموعد التوصيل قبل أن تؤكد.')" />

    <form method="POST" action="{{ route('account.orders.store') }}" class="space-y-6" data-once novalidate>
        @csrf

        <section class="card" aria-labelledby="items-title">
            <h2 id="items-title" class="text-xl">{{ __('المنتجات') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('الأسعار هنا تقديرية، وتؤكد الصيدلية السعر النهائي والتوفر قبل موافقتك.') }}</p>
            @unless ($hasSubscription)
                <p class="alert alert-warning mt-3">{{ __('طلب الأدوية متاح للمشتركين فقط. جدّد اشتراكك لمتابعة طلب دوائك.') }}
                    <a href="{{ route('account.subscription.show') }}" class="font-bold">{{ __('جدّد اشتراكك') }}</a></p>
            @endunless
            <div class="mt-4 space-y-3">
                @forelse ($products as $i => $product)
                    @continue($product->requires_prescription && ! $hasSubscription)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line p-3">
                        <div>
                            <p class="font-semibold">{{ $product->name() }}</p>
                            <p class="text-sm text-ink-soft">{{ $product->type->label() }}
                                @if ($product->requires_prescription)· <x-badge tone="info" :label="__('يُصرف بوصفة')" />@endif
                                · {{ money($product->price_halalas) }}
                                @unless ($product->in_stock)· <x-badge tone="warning" :label="__('قد لا يتوفر حاليًا')" />@endunless
                            </p>
                        </div>
                        <div>
                            <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $product->id }}">
                            <label for="qty-{{ $i }}" class="sr-only">{{ __('الكمية') }} — {{ $product->name() }}</label>
                            <select id="qty-{{ $i }}" name="items[{{ $i }}][quantity]" class="input w-28">
                                @foreach (range(0, 10) as $qty)
                                    <option value="{{ $qty }}" @selected((int) old("items.$i.quantity", $product->id === $selectedProductId ? 1 : 0) === $qty)>{{ $qty === 0 ? __('لا أريده') : $qty }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @empty
                    <p class="text-ink-soft">{{ __('لا توجد منتجات متاحة للطلب حاليًا.') }}</p>
                @endforelse
            </div>
            @error('items')<span class="error-text">{{ $message }}</span>@enderror
        </section>

        <section class="card space-y-4" aria-labelledby="rx-title">
            <h2 id="rx-title" class="text-xl">{{ __('الوصفة') }}</h2>
            <x-select name="prescription_id" :label="__('الوصفة المرتبطة بالطلب')" :value="$selectedPrescription"
                      :options="$prescriptions->mapWithKeys(fn ($p) => [$p->id => $p->medication_name.' — '.$p->dose])->all()" :placeholder="__('بدون (للمكملات فقط)')"
                      :hint="__('الأدوية تُصرف وفق الوصفة والمتطلبات ذات الصلة.')" />
        </section>

        <section class="card space-y-4" aria-labelledby="delivery-title">
            <h2 id="delivery-title" class="text-xl">{{ __('التوصيل') }}</h2>
            <x-select name="delivery_city" :label="__('المدينة')" :options="array_combine($cities, $cities)" :value="auth()->user()->city" :placeholder="__('اختر')" required />
            <x-textarea name="delivery_address" :label="__('العنوان التفصيلي')" rows="3" required />
            <x-textarea name="notes" :label="__('ملاحظات (اختياري)')" rows="2" />
            <p class="text-sm text-ink-soft">{{ __('التوصيل مجاني في: :cities (وفق التغطية وتوفر المنتج). تعتمد مدة التوصيل على المدينة وتوفر المنتج وتأكيد الصيدلية.', ['cities' => $freeList]) }}</p>
        </section>

        <button type="submit" class="btn btn-primary">{{ __('إرسال الطلب للصيدلية') }}</button>
    </form>
</x-layouts.app>
