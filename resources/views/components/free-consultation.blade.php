@php
    // The block can appear more than once on a page (home page, before the packages and at the end), so its id must be unique.
    $uid = 'free-consult-title-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
@endphp
{{-- The free-consultation call to action. Shown on the home page and right before the packages. --}}
<div role="group" aria-labelledby="{{ $uid }}" {{ $attributes->merge(['class' => 'card-peach']) }}>
    <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
        <div class="max-w-2xl">
            <h2 id="{{ $uid }}" class="text-2xl">{{ __('جرّب اتزان واحصل على استشارة مجانية') }}</h2>
            <p class="mt-2 text-lg">{{ __('استشارة مجانية واحدة للعملاء الجدد. تعرّف فيها على البرنامج والخطوة المناسبة لك قبل اختيار اشتراكك.') }}</p>
            <p class="mt-1 text-sm text-ink-soft">{{ __('لا تلزمك الاستشارة بالاشتراك، وقرار الطبيب بشأن العلاج أو الوصفة يخضع للتقييم الطبي.') }}</p>
        </div>
        <a href="{{ route('free-consultation') }}" class="btn btn-primary shrink-0">{{ __('جرّب اتزان واحصل على استشارة مجانية') }}</a>
    </div>
</div>
