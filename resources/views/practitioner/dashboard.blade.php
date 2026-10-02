@php use App\Enums\AssessmentStatus; @endphp
<x-layouts.app :title="__('لوحة المختص')">
    <x-page-header :title="$isDoctor ? __('لوحة الطبيب') : __('لوحة أخصائي التغذية')"
                   :subtitle="$practitioner && ! $practitioner->isVerified() ? __('ترخيصك لم يُوثَّق بعد، ولن تظهر للعملاء ولن تستقبل حجوزات حتى يوثّقه المسؤول.') : null" />

    @if ($staleAppointments->isNotEmpty())
        <section class="card-peach mb-6" aria-labelledby="stale-title">
            <h2 id="stale-title" class="text-xl">{{ __('مواعيد انتهت ولم تُغلق') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('أغلقها بـ «مكتملة» أو «لم يحضر» ليكتمل مسار العميل.') }}</p>
            <ul class="mt-3 divide-y divide-peach/40">
                @foreach ($staleAppointments as $appointment)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>{{ $appointment->user->name }} · {{ $appointment->scheduled_at->translatedFormat('j F، h:i A') }} · {{ $appointment->type->label() }}</span>
                        <a href="{{ route('practitioner.appointments.show', $appointment) }}" class="btn btn-secondary btn-sm">{{ __('إغلاق الموعد') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lapsedPrescriptions->isNotEmpty())
        <section class="card-peach mb-6" aria-labelledby="lapsed-title">
            <h2 id="lapsed-title" class="text-xl">{{ __('وصفات تحتاج مراجعة بعد انتهاء الاشتراك') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('لا تُوقف الوصفة تلقائيًا؛ القرار لك: أبقِها أو أوقفها.') }}</p>
            <ul class="mt-3 divide-y divide-peach/40">
                @foreach ($lapsedPrescriptions as $prescription)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>{{ $prescription->user->name }}</span>
                        <a href="{{ route('practitioner.clients.show', $prescription->user_id) }}" class="btn btn-secondary btn-sm">{{ __('فتح الملف السريري') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($isDoctor)
        @if ($myClients->isNotEmpty())
            <section class="card mb-6" aria-labelledby="mine-title">
                <h2 id="mine-title" class="text-xl">{{ __('عملائي (أتابعهم كطبيب أساسي)') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($myClients as $myClient)
                        <li><a href="{{ route('practitioner.clients.show', $myClient) }}" class="btn btn-soft btn-sm">{{ $myClient->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <a href="{{ route('practitioner.assessments.index') }}" class="card-sage no-underline">
                <p class="text-sm text-ink-soft">{{ __('تقييمات بانتظار المراجعة') }}</p>
                <p class="text-3xl font-bold text-petrol-dark">{{ $pendingAssessments }}</p>
                @if ($redFlagAssessments)<p class="mt-1"><x-badge tone="danger" :label="__(':n تحتوي على تنبيهات سلامة', ['n' => $redFlagAssessments])" /></p>@endif
            </a>
            <a href="{{ route('practitioner.labs.index') }}" class="card-sage no-underline">
                <p class="text-sm text-ink-soft">{{ __('نتائج تحاليل للمراجعة') }}</p>
                <p class="text-3xl font-bold text-petrol-dark">{{ $labsToReview }}</p>
            </a>
            <a href="{{ route('practitioner.appointments.index') }}" class="card-sage no-underline">
                <p class="text-sm text-ink-soft">{{ __('مواعيد اليوم') }}</p>
                <p class="text-3xl font-bold text-petrol-dark">{{ $todayAppointments->count() }}</p>
            </a>
        </div>

        @if ($instantQueue->isNotEmpty())
            <section class="card-peach mb-6" aria-labelledby="instant-title">
                <h2 id="instant-title" class="text-xl">{{ __('طلبات استشارة فورية') }}</h2>
                <ul class="mt-3 divide-y divide-peach/40">
                    @foreach ($instantQueue as $appointment)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>{{ $appointment->user->name }} · {{ $appointment->mode->label() }} · {{ $appointment->created_at->diffForHumans() }}</span>
                            <form method="POST" action="{{ route('practitioner.appointments.claim', $appointment) }}" data-once>
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('استلام الاستشارة') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="card mb-6" aria-labelledby="today-title">
            <h2 id="today-title" class="text-xl">{{ __('مواعيد اليوم') }}</h2>
            @forelse ($todayAppointments as $appointment)
                <p class="mt-3"><a href="{{ route('practitioner.appointments.show', $appointment) }}" class="font-semibold">{{ $appointment->scheduled_at->translatedFormat('h:i A') }}</a> · {{ $appointment->user->name }} · {{ $appointment->type->label() }} · {{ $appointment->mode->label() }}</p>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا توجد مواعيد اليوم.') }}</p>
            @endforelse
        </section>

        <section class="card" aria-labelledby="req-title">
            <h2 id="req-title" class="text-xl">{{ __('طلبات التواصل الموجهة لك') }}</h2>
            @forelse ($urgentRequests as $contact)
                <p class="mt-3">
                    @if ($contact->isUrgent())<x-badge tone="danger" :label="__('عاجل')" />@endif
                    {{ $contact->user->name }} · {{ $contact->created_at->diffForHumans() }}
                </p>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا توجد طلبات مفتوحة.') }}</p>
            @endforelse
            <a href="{{ route('care.contact-requests.index') }}" class="mt-3 inline-block">{{ __('فتح الطلبات') }}</a>
        </section>
    @else
        <section class="card" aria-labelledby="plans-title">
            <h2 id="plans-title" class="text-xl">{{ __('العملاء المسندون إليك') }}</h2>
            @forelse ($assignedPlans as $plan)
                <p class="mt-3"><a href="{{ route('practitioner.clients.show', $plan->user) }}" class="font-semibold">{{ $plan->user->name }}</a>
                    · {{ __('منذ :date', ['date' => $plan->starts_on->translatedFormat('j F Y')]) }}</p>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا يوجد عملاء مسندون إليك حاليًا.') }}</p>
            @endforelse
        </section>
    @endif
</x-layouts.app>
