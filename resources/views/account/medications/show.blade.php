@php $questions = $prescription->preDoseQuestions(); @endphp
<x-layouts.app :title="$prescription->medication_name">
    <p class="mb-2"><a href="{{ route('account.medications.index') }}">← {{ __('الأدوية والجرعات') }}</a></p>
    <x-page-header :title="$prescription->medication_name" />

    <section class="card" aria-labelledby="rx-title">
        <h2 id="rx-title" class="sr-only">{{ __('تفاصيل الوصفة') }}</h2>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-ink-soft">{{ __('الجرعة') }}</dt><dd class="text-lg font-semibold">{{ $prescription->dose }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('التكرار') }}</dt><dd class="text-lg font-semibold">{{ trans_choice('{1} كل يوم|{7} كل أسبوع|[*] كل :count يومًا', $prescription->interval_days, ['count' => $prescription->interval_days]) }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('تاريخ البدء') }}</dt><dd class="text-lg font-semibold">{{ $prescription->starts_on->translatedFormat('j F Y') }}</dd></div>
            <div><dt class="text-sm text-ink-soft">{{ __('الحالة') }}</dt><dd><x-badge :status="$prescription->status" /></dd></div>
        </dl>
        @if ($prescription->instructions)
            <p class="mt-4 rounded-xl bg-ivory p-3 whitespace-pre-line"><strong>{{ __('تعليمات الطبيب') }}:</strong> {{ $prescription->instructions }}</p>
        @endif
        <p class="mt-3 text-sm text-ink-soft">{{ __('وصفها: :name', ['name' => $prescription->practitioner->displayName()]) }}</p>
        <p class="mt-3 rounded-xl bg-peach-soft p-3">{{ __('لا يمكنك تعديل الجرعة بنفسك، ولا تُرفع الجرعات تلقائيًا. لأي تغيير تواصل مع طبيبك.') }}</p>
    </section>

    @if ($prescription->isActive() && $prescription->next_dose_at)
        <section class="card-sage mt-6" aria-labelledby="dose-title">
            <h2 id="dose-title" class="text-xl">{{ __('الجرعة القادمة') }}: {{ $prescription->next_dose_at->translatedFormat('l j F، h:i A') }}</h2>

            <form method="POST" action="{{ route('account.medications.doses.store', $prescription) }}" class="mt-4 space-y-4" data-once novalidate>
                @csrf
                @if ($questions)
                    <fieldset class="rounded-xl bg-white p-4">
                        <legend class="px-1 font-semibold">{{ __('استبيان قصير قبل الجرعة') }}</legend>
                        @foreach ($questions as $index => $question)
                            <div class="mt-3" role="group" aria-labelledby="pq-{{ $index }}">
                                <p id="pq-{{ $index }}">{{ $question['text'] }}</p>
                                <div class="mt-1 flex gap-4">
                                    @foreach (['yes' => __('نعم'), 'no' => __('لا')] as $value => $label)
                                        <label class="flex cursor-pointer items-center gap-2"><input type="radio" class="size-5 accent-petrol" name="answers[{{ $index }}]" value="{{ $value }}" @checked(old("answers.$index") === $value)> {{ $label }}</label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @error('answers')<span class="error-text">{{ $message }}</span>@enderror
                    </fieldset>
                @endif

                <x-input name="note" :label="__('ملاحظة (اختياري)')" maxlength="500" />

                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="status" value="taken" class="btn btn-primary">{{ __('أخذت الجرعة') }}</button>
                    <button type="submit" name="status" value="missed" class="btn btn-secondary">{{ __('نسيت الجرعة') }}</button>
                </div>
                <p class="text-sm text-ink-soft">{{ __('يمكنك تسجيل الجرعة قبل موعدها بساعتين. تسجيل الجرعة المنسية يساعد فريقك على دعمك، وليس للّوم.') }}</p>
            </form>
        </section>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('account.side-effects.index') }}" class="btn btn-secondary">{{ __('سجّل عَرَضًا جانبيًا') }}</a>
        <a href="{{ route('account.contact-requests.index') }}" class="btn btn-secondary">{{ __('تواصل مع الطبيب') }}</a>
        @if ($prescription->isActive() && $hasSubscription)
            <a href="{{ route('account.orders.create', ['prescription' => $prescription->id]) }}" class="btn btn-primary">{{ __('اطلب هذا الدواء') }}</a>
        @elseif ($prescription->isActive())
            <a href="{{ route('account.subscription.show') }}" class="btn btn-primary">{{ __('جدّد اشتراكك لطلب الدواء') }}</a>
        @endif
    </div>

    <section class="mt-8" aria-labelledby="log-title">
        <h2 id="log-title" class="mb-3 text-xl">{{ __('سجل الجرعات') }}</h2>
        @if ($logs->isEmpty())
            <p class="text-ink-soft">{{ __('لم تُسجَّل جرعات بعد.') }}</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">{{ __('سجل الجرعات') }}</caption>
                    <thead><tr><th scope="col">{{ __('موعد الجرعة') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col">{{ __('وقت التسجيل') }}</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr><td>{{ $log->scheduled_for->translatedFormat('j F Y، h:i A') }}</td><td><x-badge :status="$log->status" /></td><td>{{ $log->logged_at->translatedFormat('j F، h:i A') }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <x-emergency class="mt-6" />
</x-layouts.app>
