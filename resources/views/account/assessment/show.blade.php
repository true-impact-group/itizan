@php
    use App\Enums\AssessmentStatus;
    use App\Enums\Gender;
    use App\Enums\PregnancyStatus;

    $source = $assessment ?? null;                 // the open assessment (draft / needs info) or the last finished one
    $values = $editable ? ($source ?? $prefill) : $source;
    $isFemale = auth()->user()->gender === Gender::Female;
    $answers = $values?->safety_answers ?? [];
@endphp
<x-layouts.app :title="__('التقييم الطبي')">
    <x-page-header :title="__('التقييم الطبي')" :subtitle="__('يراجع الطبيب معلوماتك ليحدد ملاءمة البرنامج والخطوات المناسبة لك. معلوماتك الصحية متاحة للمخوّلين فقط.')" />

    @if ($assessment)
        <div class="card-sage mb-6">
            <p class="flex flex-wrap items-center gap-2"><strong>{{ __('حالة التقييم') }}:</strong> <x-badge :status="$assessment->status" /></p>
            @if ($assessment->status === AssessmentStatus::NeedsInfo && $assessment->reviewer_notes)
                <p class="mt-3 rounded-xl bg-white p-3"><strong>{{ __('ملاحظة الطبيب') }}:</strong> {{ $assessment->reviewer_notes }}</p>
            @elseif (in_array($assessment->status, [AssessmentStatus::Approved, AssessmentStatus::NotSuitable], true))
                <p class="mt-2">
                    {{ $assessment->status === AssessmentStatus::Approved
                        ? __('راجع الطبيب تقييمك ووجد أن البرنامج مناسب. سيُعدّ خطتك ويخبرك بالخطوات التالية.')
                        : __('راجع الطبيب تقييمك وتبيّن أن البرنامج غير مناسب لك حاليًا.') }}
                </p>
                @if ($assessment->reviewer_notes)
                    <p class="mt-3 rounded-xl bg-white p-3"><strong>{{ __('ملاحظة الطبيب') }}:</strong> {{ $assessment->reviewer_notes }}</p>
                @endif
            @elseif ($assessment->status === AssessmentStatus::Submitted)
                <p class="mt-2">{{ __('تقييمك عند الطبيب. سنرسل لك تنبيهًا عند اكتمال المراجعة.') }}</p>
            @endif
        </div>
    @endif

    @if (! $editable)
        {{-- Read-only summary --}}
        <div class="card space-y-4">
            <dl class="grid gap-4 sm:grid-cols-4">
                <div><dt class="text-sm text-ink-soft">{{ __('الطول') }}</dt><dd class="text-lg font-semibold">{{ $assessment->height_cm }} {{ __('سم') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الوزن') }}</dt><dd class="text-lg font-semibold">{{ $assessment->weight_kg }} {{ __('كجم') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('محيط الخصر') }}</dt><dd class="text-lg font-semibold">{{ $assessment->waist_cm }} {{ __('سم') }}</dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('مؤشر كتلة الجسم') }}</dt><dd class="text-lg font-semibold">{{ $assessment->bmi }} <span class="text-sm font-normal text-ink-soft">({{ $assessment->bmiCategory() }})</span></dd></div>
            </dl>
            <p class="text-sm text-ink-soft">{{ __('مؤشر كتلة الجسم للاطلاع فقط؛ يفسّره الطبيب في سياق حالتك.') }}</p>
            @if ($assessment->status !== AssessmentStatus::Submitted)
                <a href="{{ route('account.assessment.show', ['update' => 1]) }}" class="btn btn-secondary">{{ __('تحديث التقييم') }}</a>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('account.assessment.update') }}" class="space-y-6" data-once novalidate>
            @csrf @method('PUT')

            <fieldset class="card space-y-5">
                <legend class="sr-only">{{ __('القياسات') }}</legend>
                <h2 class="text-xl">{{ __('القياسات') }}</h2>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input name="height_cm" type="number" step="0.1" inputmode="decimal" :label="__('الطول (سم)')" :value="$values?->height_cm" />
                    <x-input name="weight_kg" type="number" step="0.1" inputmode="decimal" :label="__('الوزن (كجم)')" :value="$values?->weight_kg" />
                    <x-input name="waist_cm" type="number" step="0.1" inputmode="decimal" :label="__('محيط الخصر (سم)')" :value="$values?->waist_cm" />
                    <x-input name="target_weight_kg" type="number" step="0.1" inputmode="decimal" :label="__('الوزن الذي تطمح إليه (كجم)')" :value="$values?->target_weight_kg" />
                </div>
                <p class="hint">{{ __('يُحسب مؤشر كتلة الجسم تلقائيًا من الطول والوزن.') }}</p>
            </fieldset>

            <fieldset class="card space-y-5">
                <legend class="sr-only">{{ __('التاريخ الصحي') }}</legend>
                <h2 class="text-xl">{{ __('التاريخ الصحي') }}</h2>
                <x-textarea name="medical_history" :label="__('التاريخ المرضي')" :value="$values?->medical_history" rows="3" />
                <x-textarea name="chronic_conditions" :label="__('الأمراض المزمنة')" :value="$values?->chronic_conditions" rows="2" :hint="__('إن لم يوجد اكتب «لا يوجد».')" />
                <x-textarea name="current_medications" :label="__('الأدوية الحالية')" :value="$values?->current_medications" rows="2" :hint="__('إن لم يوجد اكتب «لا يوجد».')" />
                <x-textarea name="allergies" :label="__('الحساسية')" :value="$values?->allergies" rows="2" :hint="__('إن لم يوجد اكتب «لا يوجد».')" />
                <x-textarea name="past_surgeries" :label="__('العمليات السابقة')" :value="$values?->past_surgeries" rows="2" />
                <x-textarea name="previous_treatments" :label="__('تجاربك السابقة مع علاجات إدارة الوزن')" :value="$values?->previous_treatments" rows="3" />
                <x-textarea name="current_symptoms" :label="__('الأعراض الحالية')" :value="$values?->current_symptoms" rows="2" />
                <x-textarea name="available_labs" :label="__('التحاليل المتاحة لديك')" :value="$values?->available_labs" rows="2" :hint="__('اذكر أهم النتائج وتواريخها إن وجدت.')" />
            </fieldset>

            @if ($isFemale)
                <fieldset class="card space-y-5">
                    <legend class="sr-only">{{ __('الحمل والرضاعة') }}</legend>
                    <h2 class="text-xl">{{ __('الحمل والرضاعة') }}</h2>
                    <x-select name="pregnancy_status" :label="__('حالة الحمل')" :options="PregnancyStatus::options()" :value="$values?->pregnancy_status?->value" :placeholder="__('اختر')" />
                    <x-checkbox name="is_breastfeeding" :label="__('أرضع طفلًا حاليًا')" :checked="$values?->is_breastfeeding" />
                </fieldset>
            @endif

            <fieldset class="card space-y-4">
                <legend class="sr-only">{{ __('أسئلة السلامة') }}</legend>
                <h2 class="text-xl">{{ __('أسئلة السلامة') }}</h2>
                <p class="text-ink-soft">{{ __('تساعد إجاباتك الطبيب على حمايتك. الإجابة بنعم لا تمنعك تلقائيًا؛ الطبيب هو من يقرر.') }}</p>
                @foreach ($questions as $key => $question)
                    <div class="rounded-xl border border-line p-3" role="group" aria-labelledby="q-{{ $key }}">
                        <p id="q-{{ $key }}" class="font-medium">{{ __($question['text']) }}</p>
                        <div class="mt-2 flex gap-4">
                            @foreach (['yes' => __('نعم'), 'no' => __('لا')] as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="radio" class="size-5 accent-petrol" name="safety_answers[{{ $key }}]" value="{{ $value }}" @checked(old("safety_answers.$key", $answers[$key] ?? null) === $value)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @error('safety_answers')<span class="error-text">{{ $message }}</span>@enderror
            </fieldset>

            <fieldset class="card space-y-5">
                <legend class="sr-only">{{ __('أهدافك') }}</legend>
                <h2 class="text-xl">{{ __('أهدافك') }}</h2>
                <x-textarea name="goals" :label="__('ما الذي تريد تحقيقه من هذه الرحلة؟')" :value="$values?->goals" rows="3" />
            </fieldset>

            <div class="card space-y-4">
                <x-checkbox name="medical_consent" :label="__('أوافق على معالجة بياناتي الصحية ومشاركتها مع الطبيب المعالج وفريق الرعاية لأغراض تقييمي ومتابعتي فقط.')"
                            :hint="__('هذه الموافقة منفصلة عن الموافقات التسويقية، ولا تُستخدم بياناتك الصحية للتسويق.')" />
                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="action" value="save" class="btn btn-secondary">{{ __('حفظ كمسودة') }}</button>
                    <button type="submit" name="action" value="submit" class="btn btn-primary">{{ __('إرسال إلى الطبيب') }}</button>
                </div>
            </div>
        </form>
    @endif

    <x-emergency class="mt-6" />
</x-layouts.app>
