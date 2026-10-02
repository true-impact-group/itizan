<x-layouts.app :title="__('حسابي')">
    @php
        $stepsDone = collect($steps)->where('done', true)->count();
        $stepsTotal = max(count($steps), 1);
        $journeyPercent = (int) round($stepsDone / $stepsTotal * 100);
    @endphp
    {{-- "What is my next step?" answered first: greeting, encouraging journey progress, and the next action on a glass card. --}}
    <div class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-[#d6ebe2] via-petrol-soft to-[#f9dccb] p-5 sm:p-8">
        <div class="pointer-events-none absolute -top-24 size-72 rounded-full bg-petrol/15 -start-20" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 size-60 rounded-full bg-peach/70 -end-14" aria-hidden="true"></div>
        <div class="relative grid items-center gap-6 lg:grid-cols-2 lg:gap-10">
            <div>
                <h1 class="text-3xl text-ink sm:text-4xl">{{ __('أهلًا :name', ['name' => auth()->user()->name]) }}</h1>
                <p class="mt-2 text-lg text-ink">{{ __('كل خطوة في المتابعة تساعد فريقك على فهم تقدمك وتقديم دعم أنسب لك.') }}</p>
                <div class="mt-5">
                    <div class="flex items-center justify-between font-medium"><span>{{ __('رحلتك في اتزان') }}</span><b class="text-petrol">{{ __(':done من :total مراحل', ['done' => $stepsDone, 'total' => $stepsTotal]) }}</b></div>
                    <div class="mt-2 h-3 overflow-hidden rounded-full bg-white/70" role="progressbar" aria-valuenow="{{ $journeyPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('تقدم الرحلة') }}">
                        <div class="h-full rounded-full bg-gradient-to-l from-petrol to-[#2a8e80]" style="width: {{ $journeyPercent }}%"></div>
                    </div>
                </div>
            </div>

            <section class="glass rounded-[1.75rem] p-6 shadow-[0_20px_50px_-24px_rgba(15,74,67,.55)]" aria-labelledby="next-step-title">
                <p class="flex items-center gap-2 text-sm font-bold text-petrol"><span class="size-2.5 rounded-full bg-petrol" aria-hidden="true"></span>{{ __('خطوتك القادمة') }}</p>
                <h2 id="next-step-title" class="mt-2 text-2xl text-ink">{{ $nextStep['title'] }}</h2>
                <p class="mt-2 text-lg text-ink-soft">{{ $nextStep['body'] }}</p>
                <a href="{{ $nextStep['url'] }}" class="btn btn-primary mt-4">{{ $nextStep['cta'] }}</a>
            </section>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Subscription --}}
        <section class="card" aria-labelledby="sub-title">
            <h2 id="sub-title" class="text-xl">{{ __('حالة الاشتراك') }}</h2>
            @if ($subscription)
                <p class="mt-2"><x-badge :status="$subscription->status" /> <strong>{{ $subscription->package->name() }}</strong></p>
                <p class="mt-2 text-ink-soft">{{ __('ينتهي في :date (متبقي :days يومًا)', ['date' => $subscription->ends_at->translatedFormat('j F Y'), 'days' => $subscription->daysLeft()]) }}</p>
                <p class="text-ink-soft">{{ __('استشارات المتابعة المتبقية: :n', ['n' => $subscription->remainingFollowups()]) }}</p>
                <a href="{{ route('account.subscription.show') }}" class="mt-3 inline-block">{{ __('إدارة الاشتراك والفواتير') }}</a>
            @else
                <p class="mt-2 text-ink-soft">{{ __('ليس لديك اشتراك فعّال حاليًا.') }}</p>
                <a href="{{ route('account.subscription.show') }}" class="btn btn-secondary btn-sm mt-3">{{ __('عرض الباقات') }}</a>
            @endif
        </section>

        {{-- Appointments --}}
        <section class="card" aria-labelledby="appt-title">
            <h2 id="appt-title" class="text-xl">{{ __('مواعيدك القادمة') }}</h2>
            @forelse ($upcoming as $appointment)
                <p class="mt-3">
                    <a href="{{ route('account.appointments.show', $appointment) }}" class="font-semibold">{{ $appointment->type->label() }}</a>
                    · {{ $appointment->is_instant && ! $appointment->practitioner_id ? __('فورية — بانتظار الطبيب') : $appointment->scheduled_at->translatedFormat('l j F، h:i A') }}
                    <x-badge :status="$appointment->status" />
                </p>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا توجد مواعيد قادمة.') }}</p>
            @endforelse
            <a href="{{ route('account.appointments.index') }}" class="mt-3 inline-block">{{ __('كل المواعيد') }}</a>
        </section>

        {{-- Plan and progress --}}
        <section class="rounded-3xl bg-petrol-soft p-5 sm:p-6 lg:col-span-2" aria-labelledby="plan-title">
            <h2 id="plan-title" class="text-xl">{{ __('خطتك وتقدمك') }}</h2>
            @if ($plan)
                <dl class="mt-3 grid gap-3 sm:grid-cols-4">
                    <div><dt class="text-sm text-ink-soft">{{ __('وزن البداية') }}</dt><dd class="text-xl font-semibold">{{ $plan->start_weight_kg }} {{ __('كجم') }}</dd></div>
                    <div><dt class="text-sm text-ink-soft">{{ __('الوزن الحالي') }}</dt><dd class="text-xl font-semibold">{{ $latestMeasurement?->weight_kg ?? '—' }} {{ __('كجم') }}</dd></div>
                    <div><dt class="text-sm text-ink-soft">{{ __('الهدف') }}</dt><dd class="text-xl font-semibold">{{ $plan->target_weight_kg }} {{ __('كجم') }}</dd></div>
                    <div><dt class="text-sm text-ink-soft">{{ __('محيط الخصر') }}</dt><dd class="text-xl font-semibold">{{ $latestMeasurement?->waist_cm ?? $plan->start_waist_cm ?? '—' }} {{ __('سم') }}</dd></div>
                </dl>
                @if ($progress)
                    <div class="mt-4">
                        <div class="mb-1 flex justify-between text-sm"><span>{{ __('التقدم نحو الهدف') }}</span><span class="font-semibold">{{ $progress['percent'] }}%</span></div>
                        <div class="h-3 overflow-hidden rounded-full bg-white" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('التقدم نحو الهدف') }}">
                            <div class="h-full rounded-full bg-petrol" style="width: {{ $progress['percent'] }}%"></div>
                        </div>
                    </div>
                @endif
                <p class="mt-4"><a href="{{ route('account.plan.show') }}">{{ __('عرض الخطة كاملة') }}</a> · <a href="{{ route('account.progress.index') }}">{{ __('تسجيل قياس جديد') }}</a></p>
            @else
                <p class="mt-2">{{ __('ستظهر خطتك هنا بعد أن يراجع الطبيب تقييمك الطبي.') }}</p>
            @endif
        </section>

        {{-- Doses --}}
        @if ($dueDoses->isNotEmpty())
            <section class="card" aria-labelledby="dose-title">
                <h2 id="dose-title" class="text-xl">{{ __('الجرعات القادمة') }}</h2>
                @foreach ($dueDoses as $prescription)
                    <p class="mt-3">
                        <a href="{{ route('account.medications.show', $prescription) }}" class="font-semibold">{{ $prescription->medication_name }}</a>
                        · {{ $prescription->next_dose_at->translatedFormat('j F، h:i A') }}
                        @if ($prescription->isDue()) <x-badge tone="warning" :label="__('مستحقة الآن')" /> @endif
                    </p>
                @endforeach
            </section>
        @endif

        {{-- Orders --}}
        @if ($openOrders->isNotEmpty())
            <section class="card" aria-labelledby="orders-title">
                <h2 id="orders-title" class="text-xl">{{ __('طلباتك الجارية') }}</h2>
                @foreach ($openOrders as $order)
                    <p class="mt-3"><a href="{{ route('account.orders.show', $order) }}" class="font-semibold">{{ __('طلب رقم :id', ['id' => $order->id]) }}</a> <x-badge :status="$order->status" /></p>
                @endforeach
            </section>
        @endif

        {{-- Notifications --}}
        <section class="card" aria-labelledby="notif-title">
            <h2 id="notif-title" class="text-xl">{{ __('آخر الإشعارات') }}</h2>
            @forelse ($notifications as $notification)
                <p class="mt-3 {{ $notification->read_at ? 'text-ink-soft' : 'font-semibold' }}">
                    @if ($notification->data['url'] ?? null)<a href="{{ $notification->data['url'] }}">{{ $notification->data['title'] ?? '' }}</a>@else{{ $notification->data['title'] ?? '' }}@endif
                    <span class="block text-sm font-normal text-ink-soft">{{ $notification->created_at->diffForHumans() }}</span>
                </p>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا توجد إشعارات.') }}</p>
            @endforelse
            <a href="{{ route('notifications.index') }}" class="mt-3 inline-block">{{ __('كل الإشعارات') }}</a>
        </section>

        {{-- Journey --}}
        <section class="card lg:col-span-2" aria-labelledby="journey-title">
            <details>
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden"><h2 id="journey-title" class="text-xl">{{ __('مراحل رحلتك') }}</h2><span class="text-sm font-medium text-petrol">{{ __('عرض التفاصيل') }}</span></summary>
                <ol class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($steps as $i => $step)
                        <li class="flex items-center gap-3 rounded-xl bg-petrol-soft px-3 py-2">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-petrol text-white' : 'border border-petrol bg-white text-petrol' }}" aria-hidden="true">{{ $step['done'] ? '✓' : $i + 1 }}</span>
                            <span>{{ __($step['label']) }}</span>
                            <span class="sr-only">{{ $step['done'] ? __('تمت') : __('قادمة') }}</span>
                        </li>
                    @endforeach
                </ol>
            </details>
        </section>
    </div>

    <x-emergency class="mt-6" />
</x-layouts.app>
