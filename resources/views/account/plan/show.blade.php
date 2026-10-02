@php use App\Enums\AssessmentStatus; @endphp
<x-layouts.app :title="__('خطتي')">
    <x-page-header :title="__('خطة إدارة الوزن')" />

    @if (! $plan)
        @php $status = $assessment?->status; @endphp
        <x-empty :title="__('لم تُعدّ خطتك بعد')"
                 :text="match (true) {
                    $status === null, $status === AssessmentStatus::Draft => __('أكمل تقييمك الطبي ليتمكن الطبيب من إعداد خطتك.'),
                    $status === AssessmentStatus::Submitted => __('تقييمك عند الطبيب. ستظهر خطتك هنا بعد المراجعة.'),
                    $status === AssessmentStatus::NeedsInfo => __('يحتاج الطبيب إلى معلومات إضافية في تقييمك.'),
                    $status === AssessmentStatus::NotSuitable => __('راجع ملاحظة الطبيب في تقييمك.'),
                    default => __('فريقك يُعدّ خطتك الآن، وسنخبرك فور جاهزيتها.'),
                 }">
            <a href="{{ route('account.assessment.show') }}" class="btn btn-primary">{{ __('فتح التقييم الطبي') }}</a>
        </x-empty>
    @else
        <section class="card-sage" aria-labelledby="goals-title">
            <h2 id="goals-title" class="text-xl">{{ __('أرقامك') }}</h2>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-sm text-ink-soft">{{ __('وزن البداية') }}</dt><dd class="text-2xl font-semibold">{{ $plan->start_weight_kg }} <span class="text-base">{{ __('كجم') }}</span></dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الوزن الحالي') }}</dt><dd class="text-2xl font-semibold">{{ $latest?->weight_kg ?? '—' }} <span class="text-base">{{ __('كجم') }}</span></dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('الهدف') }}</dt><dd class="text-2xl font-semibold">{{ $plan->target_weight_kg }} <span class="text-base">{{ __('كجم') }}</span></dd></div>
                <div><dt class="text-sm text-ink-soft">{{ __('محيط الخصر') }}</dt><dd class="text-2xl font-semibold">{{ $latest?->waist_cm ?? $plan->start_waist_cm ?? '—' }} <span class="text-base">{{ __('سم') }}</span>
                    @if ($plan->target_waist_cm)<span class="block text-sm font-normal text-ink-soft">{{ __('الهدف: :n سم', ['n' => $plan->target_waist_cm]) }}</span>@endif</dd></div>
            </dl>
            @if ($progress)
                <div class="mt-5">
                    <div class="mb-1 flex justify-between"><span>{{ __('التقدم نحو الهدف') }}</span><span class="font-semibold">{{ $progress['percent'] }}%</span></div>
                    <div class="h-3 overflow-hidden rounded-full bg-white" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('التقدم نحو الهدف') }}">
                        <div class="h-full rounded-full bg-petrol" style="width: {{ $progress['percent'] }}%"></div>
                    </div>
                    <p class="mt-2 text-sm text-ink-soft">{{ __('التغيّر منذ البداية: :change كجم، والمتبقي للهدف: :remaining كجم', ['change' => $progress['change'], 'remaining' => $progress['remaining']]) }}</p>
                </div>
            @endif
            <p class="mt-4"><a href="{{ route('account.progress.index') }}" class="btn btn-primary btn-sm">{{ __('تسجيل قياس جديد') }}</a></p>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="card" aria-labelledby="notes-title">
                <h2 id="notes-title" class="text-xl">{{ __('ملاحظات الطبيب') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ $plan->practitioner->displayName() }}</p>
                <p class="mt-3 whitespace-pre-line">{{ $plan->doctor_notes ?: __('لا توجد ملاحظات حاليًا.') }}</p>
                @if ($plan->review_on)
                    <p class="mt-3 text-sm text-ink-soft">{{ __('موعد مراجعة الخطة: :date', ['date' => $plan->review_on->translatedFormat('j F Y')]) }}</p>
                @endif
            </section>

            <section class="card" aria-labelledby="steps-title">
                <h2 id="steps-title" class="text-xl">{{ __('الخطوات المطلوبة') }}</h2>
                @forelse ($plan->next_steps ?? [] as $step)
                    @if ($loop->first)<ul class="mt-3 space-y-2">@endif
                    <li class="flex gap-2"><span aria-hidden="true" class="text-petrol">◆</span><span>{{ $step }}</span></li>
                    @if ($loop->last)</ul>@endif
                @empty
                    <p class="mt-2 text-ink-soft">{{ __('لا توجد خطوات مطلوبة حاليًا.') }}</p>
                @endforelse
            </section>

            @if ($plan->nutrition_guidance || $plan->dietitian)
                <section class="card lg:col-span-2" aria-labelledby="nutrition-title">
                    <h2 id="nutrition-title" class="text-xl">{{ __('التوجيه الغذائي') }}</h2>
                    @if ($plan->dietitian)<p class="mt-1 text-sm text-ink-soft">{{ $plan->dietitian->displayName() }}</p>@endif
                    <p class="mt-3 whitespace-pre-line">{{ $plan->nutrition_guidance ?: __('سيضيف أخصائي التغذية توجيهه هنا.') }}</p>
                </section>
            @endif
        </div>
    @endif
</x-layouts.app>
