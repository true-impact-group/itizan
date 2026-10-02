<x-layouts.app :title="__('الوزن والتقدم')">
    <x-page-header :title="__('الوزن والتقدم')" :subtitle="__('نحتفي بالالتزام والمتابعة والتقدم الصحي، لا برقم الميزان وحده.')" />

    <section class="card-sage" aria-labelledby="chart-title">
        <h2 id="chart-title" class="mb-3 text-xl">{{ __('مخطط الوزن') }}</h2>
        <x-weight-chart :points="$series" :target="$plan?->target_weight_kg" />
    </section>

    <section class="card mt-6" aria-labelledby="add-title">
        <h2 id="add-title" class="text-xl">{{ __('إضافة قياس') }}</h2>
        <form method="POST" action="{{ route('account.progress.store') }}" class="mt-4 space-y-4" data-once novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input name="measured_on" type="date" :label="__('التاريخ')" :value="today()->toDateString()" :max="today()->toDateString()" required />
                <x-input name="weight_kg" type="number" step="0.1" inputmode="decimal" :label="__('الوزن (كجم)')" required />
                <x-input name="waist_cm" type="number" step="0.1" inputmode="decimal" :label="__('محيط الخصر (سم)')" />
            </div>
            <x-input name="note" :label="__('ملاحظة (اختياري)')" maxlength="255" />
            <button type="submit" class="btn btn-primary">{{ __('حفظ القياس') }}</button>
        </form>
    </section>

    <section class="mt-6" aria-labelledby="history-title">
        <h2 id="history-title" class="mb-3 text-xl">{{ __('سجل القياسات') }}</h2>
        @if ($measurements->isEmpty())
            <x-empty :title="__('لا توجد قياسات بعد')" :text="__('سجّل أول قياس ليبدأ مخطط تقدمك.')" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">{{ __('سجل القياسات') }}</caption>
                    <thead><tr><th scope="col">{{ __('التاريخ') }}</th><th scope="col">{{ __('الوزن (كجم)') }}</th><th scope="col">{{ __('محيط الخصر (سم)') }}</th><th scope="col">{{ __('ملاحظة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($measurements as $measurement)
                        <tr>
                            <td>{{ $measurement->measured_on->translatedFormat('j F Y') }}</td>
                            <td>{{ $measurement->weight_kg }}</td>
                            <td>{{ $measurement->waist_cm ?? '—' }}</td>
                            <td>{{ $measurement->note }}</td>
                            <td>
                                @can('delete', $measurement)
                                    <form method="POST" action="{{ route('account.progress.destroy', $measurement) }}" data-confirm="{{ __('حذف هذا القياس؟') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('حذف') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $measurements->links() }}
        @endif
    </section>
</x-layouts.app>
