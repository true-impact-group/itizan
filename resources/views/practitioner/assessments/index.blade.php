@php use App\Enums\AssessmentStatus; @endphp
<x-layouts.app :title="__('التقييمات الطبية')">
    <x-page-header :title="__('التقييمات الطبية')" :subtitle="__('الحالات ذات تنبيهات السلامة تظهر أولًا.')" />

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="label">{{ __('الحالة') }}</label>
            <select id="status" name="status" class="input" data-autosubmit>
                @foreach ([AssessmentStatus::Submitted, AssessmentStatus::NeedsInfo, AssessmentStatus::Approved, AssessmentStatus::NotSuitable] as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex min-h-12 items-center gap-2"><input type="checkbox" name="red_flags" value="1" class="size-5 accent-petrol" @checked(request()->boolean('red_flags')) data-autosubmit> {{ __('تنبيهات السلامة فقط') }}</label>
        <noscript><button class="btn btn-secondary btn-sm">{{ __('تصفية') }}</button></noscript>
    </form>

    @if ($assessments->isEmpty())
        <x-empty :title="__('لا توجد تقييمات في هذه الحالة')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('التقييمات الطبية') }}</caption>
                <thead><tr><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('مؤشر كتلة الجسم') }}</th><th scope="col">{{ __('أُرسل') }}</th><th scope="col">{{ __('سلامة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($assessments as $assessment)
                    <tr>
                        <td class="font-semibold">{{ $assessment->user->name }}</td>
                        <td>{{ $assessment->bmi ?? '—' }}</td>
                        <td>{{ $assessment->submitted_at?->diffForHumans() }}</td>
                        <td>@if ($assessment->has_red_flags)<x-badge tone="danger" :label="__('⚠ يحتاج انتباهًا')" />@else<x-badge tone="neutral" :label="__('لا تنبيهات')" />@endif</td>
                        <td><a href="{{ route('practitioner.assessments.show', $assessment) }}">{{ $assessment->status === AssessmentStatus::Submitted ? __('مراجعة') : __('عرض') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $assessments->links() }}
    @endif
</x-layouts.app>
