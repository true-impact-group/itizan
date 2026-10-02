@php use App\Enums\SideEffectStatus; @endphp
<x-layouts.app :title="__('الأعراض الجانبية')">
    <x-page-header :title="__('الأعراض الجانبية المسجّلة')" :subtitle="__('الحالات الشديدة تظهر أولًا. التصعيد يرسل طلبًا عاجلًا للطبيب.')" />

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('تصفية') }}">
        @foreach (SideEffectStatus::cases() as $option)
            <a href="{{ route('care.side-effects.index', ['status' => $option->value]) }}" class="btn btn-sm {{ $status === $option ? 'btn-primary' : 'btn-secondary' }}" @if ($status === $option) aria-current="page" @endif>{{ $option->label() }}</a>
        @endforeach
    </nav>

    @forelse ($reports as $report)
        <article class="card mb-3 {{ $report->severity === \App\Enums\Severity::Severe ? 'border-2 border-danger' : '' }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold">{{ $report->user->name }} · {{ $report->occurred_at->translatedFormat('j F Y، h:i A') }}</p>
                <p class="flex gap-2"><x-badge :status="$report->severity" /> <x-badge :status="$report->status" /></p>
            </div>
            <p class="mt-2 whitespace-pre-line">{{ $report->symptom }}</p>
            @foreach ($report->attachments as $file)<a href="{{ route('attachments.show', $file) }}" class="block text-sm">📎 {{ $file->original_name }}</a>@endforeach

            @can('handle', $report)
                <form method="POST" action="{{ route('care.side-effects.update', $report) }}" class="mt-4 grid gap-3 border-t border-line pt-4 md:grid-cols-3" data-once>
                    @csrf @method('PUT')
                    <x-select name="status" :id="'status-'.$report->id" :label="__('الإجراء')" :options="[SideEffectStatus::Reviewed->value => SideEffectStatus::Reviewed->label(), SideEffectStatus::Escalated->value => SideEffectStatus::Escalated->label()]" :value="$report->status->value" />
                    <div class="md:col-span-2"><x-textarea name="response_note" :id="'note-'.$report->id" :label="__('رد للعميل')" rows="2" :value="$report->response_note" /></div>
                    <div class="md:col-span-3"><button type="submit" class="btn btn-primary btn-sm">{{ __('حفظ') }}</button></div>
                </form>
            @endcan
        </article>
    @empty
        <x-empty :title="__('لا توجد أعراض في هذه الحالة')" />
    @endforelse
    {{ $reports->links() }}
</x-layouts.app>
