<x-layouts.site :title="__('الفريق الطبي')">
    <div class="section">
        <h1 class="text-4xl">{{ __('الفريق الطبي') }}</h1>
        <p class="mt-3 max-w-3xl text-xl text-ink-soft">{{ __('أطباء واستشاريون وأخصائيو تغذية مرخّصون. لا يظهر أي مختص هنا إلا بعد التحقق من ترخيصه.') }}</p>

        <section class="mt-10" aria-labelledby="doctors-title">
            <h2 id="doctors-title" class="text-2xl">{{ __('الأطباء والاستشاريون') }}</h2>
            @if ($doctors->isEmpty())
                <x-empty class="mt-4" :title="__('سيظهر الفريق هنا قريبًا')" :text="__('نعرض الأطباء بعد اكتمال التحقق من تراخيصهم.')" />
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($doctors as $doctor)<x-practitioner-card :practitioner="$doctor" />@endforeach
                </div>
            @endif
        </section>

        <section class="mt-10" aria-labelledby="dietitians-title">
            <h2 id="dietitians-title" class="text-2xl">{{ __('أخصائيو التغذية') }}</h2>
            @if ($dietitians->isEmpty())
                <p class="mt-3 text-ink-soft">{{ __('يقدم أخصائي التغذية التوجيه الغذائي ضمن الخطة عند انطباقه.') }}</p>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($dietitians as $dietitian)<x-practitioner-card :practitioner="$dietitian" />@endforeach
                </div>
            @endif
        </section>

        <div class="mt-10"><x-free-consultation /></div>
    </div>
</x-layouts.site>
