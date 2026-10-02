@php
    use App\Enums\AssessmentStatus;
    $client = $assessment->user;
    $answers = $assessment->safety_answers ?? [];
@endphp
<x-layouts.app :title="__('مراجعة التقييم')">
    <p class="mb-2"><a href="{{ route('practitioner.assessments.index') }}">← {{ __('التقييمات الطبية') }}</a></p>
    <x-page-header :title="$client->name">
        <x-slot:actions><x-badge :status="$assessment->status" /></x-slot:actions>
    </x-page-header>

    <section class="card mb-6" aria-labelledby="basic-title">
        <h2 id="basic-title" class="text-xl">{{ __('القياسات') }}</h2>
        <dl class="mt-3 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div><dt class="text-sm text-ink-soft">{{ __('العمر') }}</dt><dd class="font-semibold">{{ $client->birth_date?->age ?? '—' }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الجنس') }}</dt><dd class="font-semibold">{{ $client->gender?->label() ?? '—' }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الطول') }}</dt><dd class="font-semibold">{{ $assessment->height_cm }} {{ __('سم') }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الوزن') }}</dt><dd class="font-semibold">{{ $assessment->weight_kg }} {{ __('كجم') }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('محيط الخصر') }}</dt><dd class="font-semibold">{{ $assessment->waist_cm }} {{ __('سم') }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('مؤشر كتلة الجسم') }}</dt><dd class="font-semibold">{{ $assessment->bmi }} <span class="block text-sm font-normal text-ink-soft">{{ $assessment->bmiCategory() }}</span></dd></div>
        </dl>
        @if ($assessment->target_weight_kg)<p class="mt-3 text-sm text-ink-soft">{{ __('الوزن الذي يطمح إليه العميل: :n كجم', ['n' => $assessment->target_weight_kg]) }}</p>@endif
    </section>

    <section class="card mb-6 {{ $assessment->has_red_flags ? 'border-2 border-danger' : '' }}" aria-labelledby="safety-title">
        <h2 id="safety-title" class="text-xl">{{ __('أسئلة السلامة') }}
            @if ($assessment->has_red_flags)<x-badge tone="danger" :label="__('⚠ توجد إجابات تحتاج انتباهًا')" />@endif
        </h2>
        <ul class="mt-3 divide-y divide-line">
            @foreach ($questions as $key => $question)
                @php $answer = $answers[$key] ?? null; @endphp
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __($question['text']) }}</span>
                    <span>
                        @if ($answer === 'yes')<x-badge tone="danger" :label="__('نعم')" />
                        @elseif ($answer === 'no')<x-badge tone="neutral" :label="__('لا')" />
                        @else<x-badge tone="neutral" :label="__('لم يُجب')" />@endif
                    </span>
                </li>
            @endforeach
        </ul>
        @if ($assessment->pregnancy_status)
            <p class="mt-3">{{ __('الحمل') }}: <strong>{{ $assessment->pregnancy_status->label() }}</strong>@if ($assessment->is_breastfeeding) · {{ __('ترضع حاليًا') }}@endif</p>
        @endif
    </section>

    <section class="card mb-6" aria-labelledby="history-title">
        <h2 id="history-title" class="text-xl">{{ __('التاريخ الصحي') }}</h2>
        <dl class="mt-3 grid gap-4 md:grid-cols-2">
            @foreach ([
                'medical_history' => 'التاريخ المرضي',
                'chronic_conditions' => 'الأمراض المزمنة',
                'current_medications' => 'الأدوية الحالية',
                'allergies' => 'الحساسية',
                'past_surgeries' => 'العمليات السابقة',
                'previous_treatments' => 'تجارب سابقة مع علاجات إدارة الوزن',
                'current_symptoms' => 'الأعراض الحالية',
                'available_labs' => 'التحاليل المتاحة',
                'goals' => 'أهداف العميل',
            ] as $field => $label)
                <div><dt class="text-sm font-semibold text-ink-soft">{{ __($label) }}</dt><dd class="whitespace-pre-line">{{ $assessment->{$field} ?: '—' }}</dd></div>
            @endforeach
        </dl>
    </section>

    @if ($assessment->status === AssessmentStatus::Submitted)
        @can('review', $assessment)
            <form method="POST" action="{{ route('practitioner.assessments.review', $assessment) }}" class="card space-y-4" data-once novalidate>
                @csrf
                <h2 class="text-xl">{{ __('قرارك الطبي') }}</h2>
                <fieldset>
                    <legend class="label">{{ __('ملاءمة البرنامج') }}</legend>
                    <div class="space-y-2">
                        @foreach ([
                            'approved' => 'البرنامج مناسب — أُعدّ الخطة',
                            'needs_info' => 'أحتاج معلومات إضافية من العميل',
                            'not_suitable' => 'غير مناسب حاليًا',
                        ] as $value => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3 has-[:checked]:border-petrol has-[:checked]:bg-petrol-soft">
                                <input type="radio" name="decision" value="{{ $value }}" class="size-5 accent-petrol" @checked(old('decision') === $value) required> {{ __($label) }}
                            </label>
                        @endforeach
                    </div>
                    @error('decision')<span class="error-text">{{ $message }}</span>@enderror
                </fieldset>
                <x-textarea name="notes" :label="__('ملاحظة للعميل')" rows="4" :hint="__('مطلوبة عند طلب معلومات أو عند عدم الملاءمة. اكتب بلغة واضحة ومحترمة.')" />
                <button type="submit" class="btn btn-primary">{{ __('حفظ القرار وإبلاغ العميل') }}</button>
            </form>
        @endcan
    @else
        <p class="card-sage">{{ __('سبقت مراجعة هذا التقييم.') }}
            @if ($assessment->reviewer)<br>{{ __('بواسطة :name', ['name' => $assessment->reviewer->name]) }} · {{ $assessment->reviewed_at?->translatedFormat('j F Y') }}@endif
            @if ($assessment->reviewer_notes)<br>{{ $assessment->reviewer_notes }}@endif
        </p>
    @endif

    <p class="mt-6"><a href="{{ route('practitioner.clients.show', $client) }}" class="btn btn-secondary">{{ __('فتح الملف السريري للعميل') }}</a></p>
</x-layouts.app>
