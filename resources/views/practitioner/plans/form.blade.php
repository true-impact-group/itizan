@php
    use App\Enums\PlanStatus;
    $isDoctor = auth()->user()->hasRole(\App\Enums\Role::Doctor);
    $steps = old('next_steps', $plan?->next_steps ?? []);
@endphp
<x-layouts.app :title="__('خطة إدارة الوزن')">
    <p class="mb-2"><a href="{{ route('practitioner.clients.show', $client) }}">← {{ $client->name }}</a></p>
    <x-page-header :title="$plan ? __('تعديل الخطة') : __('إعداد خطة إدارة الوزن')" :subtitle="$client->name" />

    <form method="POST" action="{{ $plan ? route('practitioner.plans.update', $plan) : route('practitioner.plans.store', $client) }}" class="card max-w-3xl space-y-5" data-once novalidate>
        @csrf
        @if ($plan) @method('PUT') @endif

        @if ($isDoctor)
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="target_weight_kg" type="number" step="0.1" :label="__('الوزن المستهدف (كجم)')" :value="$plan?->target_weight_kg" required />
                <x-input name="target_waist_cm" type="number" step="0.1" :label="__('محيط الخصر المستهدف (سم)')" :value="$plan?->target_waist_cm" />
            </div>
            <x-textarea name="doctor_notes" :label="__('ملاحظات الطبيب للعميل')" rows="4" :value="$plan?->doctor_notes"
                        :hint="__('اكتب بلغة واضحة ومشجعة وواقعية، دون وعود بنتائج مضمونة أو سريعة.')" />

            <fieldset>
                <legend class="label">{{ __('الخطوات المطلوبة من العميل') }}</legend>
                <div class="space-y-2">
                    @foreach (range(0, 4) as $i)
                        <x-input :name="'next_steps['.$i.']'" :value="$steps[$i] ?? ''" :aria-label="__('الخطوة :n', ['n' => $i + 1])" maxlength="200" />
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="review_on" type="date" :label="__('موعد مراجعة الخطة')" :value="$plan?->review_on?->toDateString()" />
                <x-select name="dietitian_id" :label="__('أخصائي التغذية (اختياري)')" :options="$dietitians->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all()" :value="$plan?->dietitian_id" :placeholder="__('بدون')" />
            </div>
            @if ($plan)
                <x-select name="status" :label="__('حالة الخطة')" :options="PlanStatus::options()" :value="$plan->status->value" />
            @endif
        @endif

        @if ($plan)
            <x-textarea name="nutrition_guidance" :label="__('التوجيه الغذائي')" rows="6" :value="$plan->nutrition_guidance" />
        @endif

        <button type="submit" class="btn btn-primary">{{ __('حفظ الخطة') }}</button>
    </form>
</x-layouts.app>
