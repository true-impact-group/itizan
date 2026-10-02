@php $isFree = $type === \App\Enums\AppointmentType::FreeIntro; @endphp
<x-layouts.app :title="$isFree ? __('الاستشارة المجانية') : __('حجز استشارة متابعة')">
    <x-page-header :title="$isFree ? __('احجز استشارتك المجانية') : __('احجز استشارة متابعة')"
                   :subtitle="$isFree ? __('استشارة مجانية واحدة للعملاء الجدد. تعرّف فيها على البرنامج والخطوة المناسبة لك قبل اختيار اشتراكك.') : __('استشارات المتابعة المتبقية في باقتك: :n', ['n' => $subscription->remainingFollowups()])" />

    @if ($isFree)
        {{-- Scope and how attendance works, shown before the booking is confirmed. --}}
        <section class="card-sage mb-6" aria-labelledby="scope-title">
            <h2 id="scope-title" class="text-xl">{{ __('ما الذي تشمله الاستشارة المجانية وكيف تحضرها؟') }}</h2>
            <ul class="mt-3 space-y-2">
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('مدتها نحو :min دقيقة، تتعرّف فيها على البرنامج وخطواته وتطرح أسئلتك على الطبيب.', ['min' => config('itizan.booking.default_slot_minutes')]) }}</li>
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('لا تلزمك الاستشارة بالاشتراك؛ بعدها تقرر إن كنت تريد متابعة البرنامج.') }}</li>
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('قرار الطبيب بشأن العلاج أو الوصفة يخضع للتقييم الطبي، وقد لا يُوصف علاج في الاستشارة الأولى.') }}</li>
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ __('تُقدَّم عبر منصة الطب الاتصالي FTMS بالفيديو أو الصوت أو المحادثة. تجد رابط الدخول في صفحة موعدك، ويفتح قبل الموعد بعشر دقائق.') }}</li>
            </ul>
        </section>
    @endif

    @if ($primary)
        <section class="card-sage mb-6" aria-labelledby="doctor-title">
            <h2 id="doctor-title" class="text-xl">{{ __('طبيبك المتابع') }}</h2>
            <p class="mt-1 text-lg font-semibold">{{ $primary->displayName() }} <span class="font-normal text-ink-soft">— {{ $primary->specialty() }}</span></p>
            <p class="mt-2">
                @if ($with === 'primary')
                    {{ __('تظهر لك مواعيد طبيبك المتابع فقط.') }}
                    <a href="{{ route('account.appointments.create', ['type' => $type->value, 'date' => $date->toDateString(), 'with' => 'any']) }}">{{ __('عرض مواعيد أي طبيب متاح') }}</a>
                @else
                    {{ __('تظهر لك مواعيد كل الأطباء المتاحين.') }}
                    <a href="{{ route('account.appointments.create', ['type' => $type->value, 'date' => $date->toDateString(), 'with' => 'primary']) }}">{{ __('العودة إلى مواعيد طبيبي') }}</a>
                @endif
            </p>
        </section>
    @endif

    {{-- Step 1: choose the day (plain GET form, works without JavaScript) --}}
    <form method="GET" action="{{ route('account.appointments.create') }}" class="card mb-6">
        <input type="hidden" name="type" value="{{ $type->value }}">
        <input type="hidden" name="with" value="{{ $with }}">
        <label for="date" class="label">{{ __('اختر اليوم') }}</label>
        <div class="flex flex-wrap items-center gap-3">
            <select id="date" name="date" class="input max-w-xs" data-autosubmit>
                @foreach ($days as $day)
                    <option value="{{ $day->toDateString() }}" @selected($day->isSameDay($date))>{{ $day->translatedFormat('l j F') }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">{{ __('عرض الأوقات') }}</button>
        </div>
    </form>

    {{-- Step 2: choose how and when --}}
    <form method="POST" action="{{ route('account.appointments.store') }}" class="card space-y-6" data-once>
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">
        @if ($with === 'primary')
            <input type="hidden" name="practitioner_id" value="{{ $primary->id }}">
        @endif

        <fieldset>
            <legend class="label">{{ __('طريقة الاستشارة') }}</legend>
            <div class="flex flex-wrap gap-3">
                @foreach ($modes as $mode)
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-4 py-2.5 has-[:checked]:border-petrol has-[:checked]:bg-petrol-soft">
                        <input type="radio" name="mode" value="{{ $mode->value }}" class="accent-petrol" @checked(old('mode', 'video') === $mode->value) required>
                        {{ $mode->label() }}
                    </label>
                @endforeach
            </div>
            @error('mode')<span class="error-text">{{ $message }}</span>@enderror
        </fieldset>

        <fieldset>
            <legend class="label">{{ __('الأوقات المتاحة يوم :day', ['day' => $date->translatedFormat('l j F')]) }}</legend>
            @if ($times->isEmpty())
                <p class="text-ink-soft">{{ __('لا توجد أوقات متاحة في هذا اليوم. جرّب يومًا آخر أو اختر استشارة فورية.') }}</p>
            @else
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                    @foreach ($times as $time)
                        <label class="flex cursor-pointer items-center justify-center rounded-xl border border-line px-2 py-2.5 has-[:checked]:border-petrol has-[:checked]:bg-petrol has-[:checked]:text-white">
                            <input type="radio" name="starts_at" value="{{ $time->format('Y-m-d H:i') }}" class="sr-only" @checked(old('starts_at') === $time->format('Y-m-d H:i'))>
                            {{ $time->translatedFormat('h:i A') }}
                        </label>
                    @endforeach
                </div>
            @endif
            @error('starts_at')<span class="error-text">{{ $message }}</span>@enderror
        </fieldset>

        @if ($instantAvailable)
            <x-checkbox name="instant" :label="__('أحتاج استشارة فورية الآن (يتواصل معك أول طبيب متاح)')" />
        @endif

        <x-textarea name="notes" :label="__('ما الذي تود مناقشته؟ (اختياري)')" rows="3"
                    :hint="__('لا تكتب معلومات صحية تفصيلية هنا؛ ستقدمها في التقييم الطبي.')" />

        <x-checkbox name="acknowledge" :label="__('فهمت نطاق الاستشارة وطريقة حضورها عبر FTMS، وأوافق على إجرائها عن بُعد.')" />

        <button type="submit" class="btn btn-primary">{{ __('تأكيد الحجز') }}</button>
    </form>

    <x-emergency class="mt-6" />
</x-layouts.app>
