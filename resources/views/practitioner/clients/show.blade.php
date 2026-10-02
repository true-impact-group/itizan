@php use App\Enums\LabStatus; @endphp
<x-layouts.app :title="$client->name">
    <p class="mb-2"><a href="{{ route('practitioner.clients.index') }}">← {{ __('العملاء') }}</a></p>
    <x-page-header :title="$client->name" :subtitle="trim(($client->gender?->label() ?? '').' · '.($client->birth_date ? __(':n سنة', ['n' => $client->birth_date->age]) : '').' · '.($client->city ?? ''), ' ·')" />

    {{-- Assessment --}}
    <section class="card mb-6" aria-labelledby="as-title">
        <h2 id="as-title" class="text-xl">{{ __('التقييم الطبي') }}</h2>
        @if ($assessment)
            <p class="mt-2 flex flex-wrap items-center gap-3"><x-badge :status="$assessment->status" />
                @if ($assessment->bmi) {{ __('مؤشر كتلة الجسم: :n', ['n' => $assessment->bmi]) }}@endif
                @if ($isDoctor)<a href="{{ route('practitioner.assessments.show', $assessment) }}">{{ __('فتح التقييم الكامل') }}</a>@endif</p>
            @if ($assessment->goals)<p class="mt-2 text-ink-soft"><strong>{{ __('أهداف العميل') }}:</strong> {{ $assessment->goals }}</p>@endif
        @else
            <p class="mt-2 text-ink-soft">{{ __('لم يبدأ العميل تقييمه بعد.') }}</p>
        @endif
    </section>

    {{-- Plan --}}
    <section class="card-sage mb-6" aria-labelledby="plan-title">
        <h2 id="plan-title" class="text-xl">{{ __('خطة إدارة الوزن') }}</h2>
        @if ($plan)
            <dl class="mt-3 grid gap-3 sm:grid-cols-4">
                <div><dt class="text-sm text-ink-soft">{{ __('وزن البداية') }}</dt><dd class="font-semibold">{{ $plan->start_weight_kg }} {{ __('كجم') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الوزن الحالي') }}</dt><dd class="font-semibold">{{ $measurements->first()?->weight_kg ?? '—' }} {{ __('كجم') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الهدف') }}</dt><dd class="font-semibold">{{ $plan->target_weight_kg }} {{ __('كجم') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('أخصائي التغذية') }}</dt><dd class="font-semibold">{{ $plan->dietitian?->displayName() ?? '—' }}</dd></div>
            </dl>
            @can('update', $plan)
                <a href="{{ route('practitioner.plans.edit', $plan) }}" class="btn btn-primary btn-sm mt-4">{{ $isDoctor ? __('تعديل الخطة') : __('إضافة التوجيه الغذائي') }}</a>
            @endcan
        @else
            <p class="mt-2">{{ __('لا توجد خطة فعّالة.') }}</p>
            @if ($isDoctor && $assessment?->status === \App\Enums\AssessmentStatus::Approved)
                <a href="{{ route('practitioner.plans.create', $client) }}" class="btn btn-primary btn-sm mt-3">{{ __('إعداد خطة') }}</a>
            @endif
        @endif
    </section>

    {{-- Measurements --}}
    <section class="card mb-6" aria-labelledby="ms-title">
        <h2 id="ms-title" class="text-xl">{{ __('آخر القياسات') }}</h2>
        @if ($measurements->isEmpty())
            <p class="mt-2 text-ink-soft">{{ __('لا توجد قياسات.') }}</p>
        @else
            <div class="table-wrap mt-3">
                <table class="table">
                    <caption class="sr-only">{{ __('آخر القياسات') }}</caption>
                    <thead><tr><th scope="col">{{ __('التاريخ') }}</th><th scope="col">{{ __('الوزن (كجم)') }}</th><th scope="col">{{ __('محيط الخصر (سم)') }}</th></tr></thead>
                    <tbody>@foreach ($measurements as $m)<tr><td>{{ $m->measured_on->translatedFormat('j F Y') }}</td><td>{{ $m->weight_kg }}</td><td>{{ $m->waist_cm ?? '—' }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($isDoctor)
        {{-- Prescriptions --}}
        <section class="card mb-6" aria-labelledby="rx-title">
            <h2 id="rx-title" class="text-xl">{{ __('الوصفات') }}</h2>
            @forelse ($prescriptions as $prescription)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                    <p><strong>{{ $prescription->medication_name }}</strong> · {{ $prescription->dose }} · <x-badge :status="$prescription->status" />
                        <span class="block text-sm text-ink-soft">{{ $prescription->practitioner->displayName() }}@if ($prescription->next_dose_at) · {{ __('الجرعة القادمة') }}: {{ $prescription->next_dose_at->translatedFormat('j F، h:i A') }}@endif</span></p>
                    @can('update', $prescription)
                        @if ($prescription->isActive())
                            <form method="POST" action="{{ route('practitioner.prescriptions.stop', $prescription) }}" class="flex flex-wrap items-end gap-2" data-confirm="{{ __('إيقاف هذه الوصفة؟') }}" data-once>
                                @csrf
                                <input name="reason" required minlength="3" maxlength="255" class="input w-56" placeholder="{{ __('سبب الإيقاف') }}" aria-label="{{ __('سبب الإيقاف') }}">
                                <button type="submit" class="btn btn-danger btn-sm">{{ __('إيقاف') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا توجد وصفات.') }}</p>
            @endforelse

            @if ($assessment?->status === \App\Enums\AssessmentStatus::Approved && ! $hasSubscription)
                <p class="alert alert-warning mt-5">{{ __('لا يمكن كتابة وصفة لعميل ليس لديه اشتراك فعّال.') }}</p>
            @endif
            @if ($assessment?->status === \App\Enums\AssessmentStatus::Approved && $hasSubscription)
                <details class="mt-5 rounded-xl border border-line p-4" @if ($errors->hasAny(['medication_name', 'dose', 'interval_days', 'dose_time', 'starts_on'])) open @endif>
                    <summary class="cursor-pointer font-semibold text-petrol-dark">{{ __('كتابة وصفة جديدة') }}</summary>
                    <form method="POST" action="{{ route('practitioner.prescriptions.store', $client) }}" class="mt-4 space-y-4" data-once novalidate>
                        @csrf
                        <p class="text-sm text-ink-soft">{{ __('أنت من يحدد الدواء والجرعة. لا تُرفع الجرعات تلقائيًا ولا يستطيع العميل تعديلها.') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input name="medication_name" :label="__('اسم الدواء')" required />
                            <x-input name="dose" :label="__('الجرعة')" required />
                        </div>
                        <x-select name="product_id" :label="__('ربط بمنتج من الكتالوج (اختياري)')" :options="$medications->mapWithKeys(fn ($p) => [$p->id => $p->name()])->all()" :placeholder="__('بدون')" />
                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-select name="interval_days" :label="__('التكرار')" :options="[1 => __('كل يوم'), 7 => __('كل أسبوع'), 14 => __('كل أسبوعين'), 30 => __('كل شهر')]" value="1" required />
                            <x-input name="dose_time" type="time" :label="__('وقت الجرعة')" value="09:00" required />
                            <x-input name="starts_on" type="date" :label="__('تاريخ البدء')" :value="today()->toDateString()" :min="today()->toDateString()" required />
                            <x-input name="ends_on" type="date" :label="__('تاريخ الانتهاء (اختياري)')" />
                        </div>
                        <x-textarea name="instructions" :label="__('تعليمات للعميل')" rows="3" />
                        <fieldset class="rounded-xl bg-ivory p-3">
                            <legend class="px-1 font-semibold">{{ __('استبيان قبل الجرعة (اختياري)') }}</legend>
                            <p class="text-sm text-ink-soft">{{ __('إن كانت إجابة العميل هي المحددة فلن تُسجَّل الجرعة وسيصلك طلب عاجل.') }}</p>
                            @foreach (range(0, 2) as $i)
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div class="sm:col-span-2"><x-input :name="'pre_dose_questions['.$i.'][text]'" :label="__('السؤال :n', ['n' => $i + 1])" maxlength="200" /></div>
                                    <x-select :name="'pre_dose_questions['.$i.'][alert_on]'" :label="__('ينبّه عند الإجابة')" :options="['yes' => __('نعم'), 'no' => __('لا')]" value="yes" />
                                </div>
                            @endforeach
                        </fieldset>
                        <button type="submit" class="btn btn-primary">{{ __('حفظ الوصفة') }}</button>
                    </form>
                </details>
            @endif
        </section>

        {{-- Labs --}}
        <section class="card mb-6" aria-labelledby="lab-title">
            <h2 id="lab-title" class="text-xl">{{ __('التحاليل') }}</h2>
            @forelse ($labs as $lab)
                <div class="mt-3 border-t border-line pt-3">
                    <p><strong>{{ $lab->name }}</strong> <x-badge :status="$lab->status" /></p>
                    @foreach ($lab->attachments as $file)<a href="{{ route('attachments.show', $file) }}" class="block text-sm">📎 {{ $file->original_name }}</a>@endforeach
                    @can('review', $lab)
                        <form method="POST" action="{{ route('practitioner.labs.review', $lab) }}" class="mt-2 space-y-2" data-once>
                            @csrf
                            <x-textarea name="notes" :label="__('ملاحظات على النتيجة')" rows="2" />
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('تمت المراجعة') }}</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="mt-2 text-ink-soft">{{ __('لا توجد تحاليل.') }}</p>
            @endforelse

            <details class="mt-5 rounded-xl border border-line p-4">
                <summary class="cursor-pointer font-semibold text-petrol-dark">{{ __('طلب تحليل') }}</summary>
                <form method="POST" action="{{ route('practitioner.labs.store', $client) }}" class="mt-4 space-y-4" data-once novalidate>
                    @csrf
                    <x-input name="name" :label="__('اسم التحليل')" required />
                    <x-input name="instructions" :label="__('تعليمات (اختياري)')" />
                    <x-input name="due_on" type="date" :label="__('مطلوب قبل (اختياري)')" />
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('إرسال الطلب') }}</button>
                </form>
            </details>
        </section>
    @endif

    {{-- Appointments --}}
    <section class="card" aria-labelledby="ap-title">
        <h2 id="ap-title" class="text-xl">{{ __('المواعيد') }}</h2>
        @forelse ($appointments as $appointment)
            <p class="mt-2">{{ $appointment->scheduled_at->translatedFormat('j F Y، h:i A') }} · {{ $appointment->type->label() }} · <x-badge :status="$appointment->status" /></p>
        @empty
            <p class="mt-2 text-ink-soft">{{ __('لا توجد مواعيد.') }}</p>
        @endforelse
    </section>
</x-layouts.app>
