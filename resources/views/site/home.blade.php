<x-layouts.site :title="__('رحلتك لوزن صحي… تبدأ باتزان')" flush>
    {{-- 1. Hero: the value and the first step --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -top-28 size-[26rem] rounded-full bg-petrol-soft -start-36" aria-hidden="true"></div>
        <div class="section relative grid items-center gap-9 !pt-8 lg:grid-cols-[1.1fr_.9fr] lg:gap-16 lg:!pt-16">
            <div>
                <span class="eyebrow">{{ __('برنامج إدارة الوزن بإشراف طبي') }}</span>
                <h1 class="text-4xl font-bold leading-snug text-ink sm:text-5xl lg:text-6xl [text-wrap:balance]">{{ __('رحلتك لوزن صحي… تبدأ باتزان') }}</h1>
                <p class="mt-5 max-w-xl text-xl text-ink-soft">{{ __('رعاية طبية ومتابعة مستمرة تساعدك تبدأ بخطة تناسبك، وتكمل بثقة.') }}</p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('free-consultation') }}" class="btn btn-primary text-lg"><x-icon name="arrow" :size="20" class="rtl:rotate-0 ltr:rotate-180" />{{ __('ابدأ باستشارة مجانية') }}</a>
                    <a href="{{ route('how-it-works') }}" class="btn btn-secondary text-lg">{{ __('تعرّف على البرنامج') }}</a>
                </div>
                <p class="mt-4 max-w-xl text-sm text-ink-soft">{{ __('الاستشارة المجانية تعريفية بالبرنامج، وليست تشخيصًا ولا وصفًا للعلاج.') }}</p>
            </div>

            <div class="relative mx-auto w-full max-w-md">
                <div class="aspect-[4/5] overflow-hidden rounded-t-[999px] rounded-b-[2rem] bg-gradient-to-b from-peach-soft to-peach shadow-[0_10px_30px_-12px_rgba(32,51,47,.25)]">
                    <x-hero-art />
                </div>
                <span class="glass absolute top-[18%] -start-2 flex items-center gap-2.5 rounded-2xl py-2.5 pe-4 ps-4 font-bold shadow-[0_12px_30px_-12px_rgba(32,51,47,.35)] sm:-start-6">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-petrol-soft text-petrol"><x-icon name="heart" :size="19" /></span>{{ __('متابعة مستمرة') }}
                </span>
                <span class="glass absolute bottom-[14%] -end-2 flex items-center gap-2.5 rounded-2xl py-2.5 pe-4 ps-4 font-bold shadow-[0_12px_30px_-12px_rgba(32,51,47,.35)] sm:-end-6">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-petrol-soft text-petrol"><x-icon name="plan" :size="19" /></span>{{ __('خطة تناسبك') }}
                </span>
            </div>
        </div>
    </section>

    {{-- 2. You do not face it alone --}}
    <section class="bg-petrol-soft" aria-labelledby="alone-title">
        <div class="section grid gap-9 md:grid-cols-[1fr_1.1fr] md:gap-16">
            <div class="rv">
                <span class="eyebrow !bg-white">{{ __('نفهمك') }}</span>
                <h2 id="alone-title" class="text-3xl sm:text-4xl">{{ __('ما تحتاج تواجه الرحلة وحدك') }}</h2>
                <p class="mt-4 text-lg font-bold text-petrol">{{ __('نبدأ من وضعك أنت، بدون لوم وبدون أحكام.') }}</p>
            </div>
            <ul class="rv divide-y divide-petrol/20">
                @foreach ([
                    ['question', 'جرّبت حلولًا كثيرة ولم تستمر معك، وتبغى طريقة تناسب حياتك.'],
                    ['hand', 'لا تعرف من أين تبدأ، وتحتاج من يشرح لك الخطوة الأولى بهدوء.'],
                    ['steth', 'عندك حالة صحية أو أدوية، وتريد رعاية آمنة يشرف عليها طبيب.'],
                    ['chat', 'تحتاج شخصًا يتابعك ويجاوب أسئلتك في الوقت الذي تحتاجه.'],
                ] as [$icon, $text])
                    <li class="flex gap-4 py-5 text-lg font-medium text-ink"><x-icon :name="$icon" :size="26" class="mt-1 shrink-0 text-petrol" />{{ __($text) }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 3. How to start --}}
    <section class="section" aria-labelledby="how-title">
        <div class="rv max-w-xl"><span class="eyebrow">{{ __('ثلاث خطوات') }}</span><h2 id="how-title" class="text-3xl sm:text-4xl">{{ __('كيف تبدأ؟') }}</h2></div>
        <ol class="rv mt-10 grid gap-9 md:grid-cols-3 md:gap-8">
            @foreach ([
                ['استشارة أولى', 'تحجز استشارة مجانية تتعرّف فيها على البرنامج وكيف تسير الرحلة.'],
                ['خطة مناسبة', 'تعبّئ تقييمك الصحي، ويراجعه الطبيب ويضع لك خطة تناسبك.'],
                ['متابعة مستمرة', 'تتابع تقدّمك ومواعيدك، ويبقى فريقك قريبًا منك في كل مرحلة.'],
            ] as $i => [$title, $text])
                <li class="relative ps-[5.25rem] md:ps-0 md:pt-[5.25rem]">
                    <span class="absolute start-0 top-0 flex size-[3.75rem] items-center justify-center rounded-full bg-petrol text-2xl font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    @unless ($loop->last)
                        <span class="absolute start-[1.8rem] top-[3.75rem] -bottom-9 w-0.5 border-s-2 border-dashed border-petrol/35 md:start-[4.75rem] md:-end-8 md:bottom-auto md:top-[1.8rem] md:h-0 md:w-auto md:border-s-0 md:border-t-2" aria-hidden="true"></span>
                    @endunless
                    <h3 class="text-xl text-ink">{{ __($title) }}</h3>
                    <p class="mt-1 text-ink-soft">{{ __($text) }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- 4. What the programme includes --}}
    <section class="border-y border-line bg-white" aria-labelledby="services-title">
        <div class="section">
            <div class="rv max-w-xl"><span class="eyebrow">{{ __('ما يشمله البرنامج') }}</span><h2 id="services-title" class="text-3xl sm:text-4xl">{{ __('كل ما تحتاجه في مكان واحد') }}</h2></div>
            <div class="mt-10 grid gap-4 md:grid-cols-6">
                <article class="rv rounded-3xl bg-petrol p-6 text-white md:col-span-3 md:row-span-2">
                    <span class="icon-tile !bg-white/15 !text-white"><x-icon name="steth" :size="27" /></span>
                    <h3 class="text-xl !text-white">{{ __('إشراف طبي حقيقي') }}</h3>
                    <p class="mt-2 text-white/90">{{ __('طبيب يراجع تقييمك ويضع خطتك. العلاج والدواء يقررهما الطبيب وحده، وليس التطبيق ولا العميل.') }}</p>
                </article>
                @foreach ([
                    ['leaf', 'أخصائي تغذية', 'إرشادات غذائية مناسبة لنمط حياتك.', 'bg-peach-soft', 'md:col-span-3'],
                    ['trend', 'تقدّم تراه بوضوح', 'رحلتك كاملة: القياسات والمواعيد والخطوات.', 'bg-petrol-soft', 'md:col-span-3'],
                    ['clock', 'تذكيرات هادئة', 'تنبيهات المواعيد والجرعات دون إزعاج.', 'bg-ivory', 'md:col-span-2'],
                    ['box', 'طلب وتوصيل', 'الدواء بوصفة الطبيب للمشتركين، والمكمّلات والتحاليل من صيدلية مرخّصة.', 'bg-ivory', 'md:col-span-2'],
                    ['whatsapp', 'دعم عبر واتساب', 'فريق يرد عليك عند الحاجة.', 'bg-ivory', 'md:col-span-2'],
                ] as [$icon, $title, $text, $bg, $span])
                    <article class="rv {{ $bg }} {{ $span }} rounded-3xl p-6 transition duration-200 hover:-translate-y-1 hover:shadow-[0_10px_30px_-12px_rgba(32,51,47,.18)]">
                        <span class="icon-tile {{ $bg === 'bg-petrol-soft' ? '!bg-white' : '' }}"><x-icon :name="$icon" :size="27" /></span>
                        <h3 class="text-xl text-ink">{{ __($title) }}</h3>
                        <p class="mt-1 text-ink-soft">{{ __($text) }}</p>
                    </article>
                @endforeach
            </div>
            <p class="rv mt-5 rounded-e-xl border-s-4 border-peach bg-peach-soft px-4 py-2 text-sm text-ink">{{ __('القرار الطبي للطبيب وحده؛ فهو يحدد ملاءمة العلاج والوصفة والجرعة. لا تصدر المنصة وصفات آلية ولا ترفع الجرعات تلقائيًا.') }}</p>
        </div>
    </section>

    {{-- 5. The medical team --}}
    <section class="section" aria-labelledby="team-title">
        <div class="rv max-w-xl"><span class="eyebrow">{{ __('فريقك') }}</span><h2 id="team-title" class="text-3xl sm:text-4xl">{{ __('الفريق الطبي') }}</h2></div>
        @forelse ($doctors as $doctor)
            @if ($loop->first)<div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">@endif
            <div class="rv"><x-practitioner-card :practitioner="$doctor" /></div>
            @if ($loop->last)</div>@endif
        @empty
            <p class="mt-4 text-ink-soft">{{ __('تُعرض أسماء الأطباء وتراخيصهم هنا بعد التحقق منها.') }}</p>
        @endforelse
        <p class="mt-5"><a href="{{ route('team') }}">{{ __('تعرّف على الفريق الطبي') }}</a></p>
    </section>

    {{-- 6. Packages --}}
    <section class="bg-gradient-to-b from-ivory to-petrol-soft" aria-labelledby="packages-title">
        <div class="section">
            <div class="rv max-w-xl"><span class="eyebrow">{{ __('الباقات') }}</span><h2 id="packages-title" class="text-3xl sm:text-4xl">{{ __('الباقات والأسعار') }}</h2>
                <p class="mt-3 text-lg text-ink-soft">{{ __('باقتان مدفوعتان فقط، بأسعار واضحة قبل الدفع.') }}</p></div>
            <div class="mx-auto mt-10 grid max-w-4xl gap-6 md:grid-cols-2">
                @foreach ($packages as $package)
                    <div class="rv"><x-package-card :package="$package" :savings="$savings" /></div>
                @endforeach
            </div>
            <p class="mt-5 text-center"><a href="{{ route('pricing') }}">{{ __('تفاصيل الأسعار وخيارات الدفع والسياسات') }}</a></p>
        </div>
    </section>

    {{-- Privacy & safety --}}
    <section class="section" aria-labelledby="safety-title">
        <div class="rv rounded-3xl bg-petrol-soft p-6 sm:p-8">
            <h2 id="safety-title" class="text-2xl">{{ __('خصوصيتك وسلامتك') }}</h2>
            <ul class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ([
                    'بياناتك الصحية متاحة للموظفين المخوّلين فقط بحسب أدوارهم.',
                    'لا نستخدم بياناتك الصحية لأغراض التسويق.',
                    'موافقاتك الطبية والتسويقية والتجديد منفصلة.',
                    'المعلومات الصحية الحساسة تُرسل عبر حسابك، وليس عبر واتساب.',
                ] as $line)
                    <li class="flex gap-2.5"><x-icon name="check" :size="22" class="mt-1 shrink-0 text-petrol" />{{ __($line) }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 7. FAQ --}}
    @if ($faqs->isNotEmpty())
        <section class="section !pt-0" aria-labelledby="faq-title">
            <div class="rv text-center"><span class="eyebrow">{{ __('أسئلة شائعة') }}</span><h2 id="faq-title" class="text-3xl sm:text-4xl">{{ __('عندك سؤال؟') }}</h2></div>
            <div class="rv mx-auto mt-8 max-w-3xl space-y-3">
                @foreach ($faqs as $faq)
                    <details class="group rounded-2xl border border-line bg-white px-5">
                        <summary class="flex min-h-[3.75rem] cursor-pointer list-none items-center justify-between gap-4 text-lg font-bold text-ink [&::-webkit-details-marker]:hidden">
                            {{ $faq->question() }}
                            <span class="text-2xl text-petrol transition group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="pb-5 text-ink-soft">{{ $faq->answer() }}</p>
                    </details>
                @endforeach
            </div>
            <p class="mt-5 text-center"><a href="{{ route('faq') }}">{{ __('كل الأسئلة الشائعة') }}</a></p>
        </section>
    @endif

    {{-- 8. Final call --}}
    <section class="section !pt-0" aria-labelledby="final-title">
        <div role="group" aria-labelledby="final-title" class="rv relative overflow-hidden rounded-[2rem] bg-petrol px-6 py-12 text-center text-white sm:py-16">
            <div class="pointer-events-none absolute -bottom-20 size-60 rounded-full bg-peach/25 -end-14" aria-hidden="true"></div>
            <h2 id="final-title" class="relative text-3xl !text-white sm:text-4xl">{{ __('خطوتك الأولى أبسط مما تتخيل') }}</h2>
            <p class="relative mx-auto mt-3 max-w-xl text-lg text-white/90">{{ __('استشارة مجانية واحدة للعملاء الجدد. تعرّف فيها على البرنامج والخطوة المناسبة لك قبل اختيار اشتراكك.') }}</p>
            <a href="{{ route('free-consultation') }}" class="btn btn-peach relative mt-7 text-lg">{{ __('ابدأ باستشارة مجانية') }}</a>
            <p class="relative mt-4 text-sm text-white/80">{{ __('لا تلزمك الاستشارة بالاشتراك، وقرار الطبيب بشأن العلاج أو الوصفة يخضع للتقييم الطبي.') }}</p>
        </div>
    </section>
</x-layouts.site>
