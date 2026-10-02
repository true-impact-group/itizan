@props(['compact' => false])
<div role="note" {{ $attributes->merge(['class' => 'alert alert-danger']) }}>
    <strong>{{ __('اتزان ليست خدمة طوارئ.') }}</strong>
    {{ __('في الحالات الطارئة اتصل بالهلال الأحمر على :number أو توجه إلى أقرب طوارئ.', ['number' => config('itizan.emergency_number')]) }}
    @unless ($compact)
        <a href="tel:{{ config('itizan.emergency_number') }}" class="mt-1 block font-bold text-danger underline">
            {{ __('اتصال بالرقم :number', ['number' => config('itizan.emergency_number')]) }}
        </a>
    @endunless
</div>
