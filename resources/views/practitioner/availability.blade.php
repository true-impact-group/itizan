@php
    $days = [0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
@endphp
<x-layouts.app :title="__('أوقات التوفر')">
    <x-page-header :title="__('أوقات التوفر الأسبوعية')" :subtitle="__('يحجز العملاء فقط ضمن هذه الأوقات. يمكنك إضافة حتى ثلاث فترات في اليوم.')" />

    <form method="POST" action="{{ route('practitioner.availability.update') }}" class="card space-y-5" data-once novalidate>
        @csrf @method('PUT')
        <x-select name="slot_minutes" :label="__('مدة الاستشارة (دقيقة)')" :options="[10 => 10, 15 => 15, 20 => 20, 30 => 30, 45 => 45, 60 => 60]" :value="$slotMinutes" required class="max-w-xs" />

        @foreach ($days as $weekday => $label)
            @php $rows = ($windows[$weekday] ?? collect())->values(); @endphp
            <fieldset class="rounded-xl border border-line p-4">
                <legend class="px-2 font-semibold">{{ __($label) }}</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (range(0, 2) as $i)
                        <div class="grid grid-cols-2 gap-2">
                            <x-input :name="'days['.$weekday.']['.$i.'][start]'" type="time" :label="__('من')" :value="isset($rows[$i]) ? substr($rows[$i]->start_time, 0, 5) : ''" />
                            <x-input :name="'days['.$weekday.']['.$i.'][end]'" type="time" :label="__('إلى')" :value="isset($rows[$i]) ? substr($rows[$i]->end_time, 0, 5) : ''" />
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
