@php use App\Enums\Severity; @endphp
<x-layouts.app :title="__('الأعراض الجانبية')">
    <x-page-header :title="__('الأعراض الجانبية')" :subtitle="__('سجّل ما تشعر به ليطّلع عليه فريقك الطبي ويقدم لك الدعم المناسب.')" />

    <x-emergency class="mb-6" />

    <section class="card" aria-labelledby="new-title">
        <h2 id="new-title" class="text-xl">{{ __('تسجيل عَرَض جانبي') }}</h2>
        <form method="POST" action="{{ route('account.side-effects.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4" data-once novalidate>
            @csrf
            <x-textarea name="symptom" :label="__('ماذا تشعر؟')" rows="3" required />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select name="severity" :label="__('الشدة')" :options="Severity::options()" :placeholder="__('اختر')" required />
                <x-input name="occurred_at" type="datetime-local" :label="__('متى بدأ؟')" :value="now()->format('Y-m-d\TH:i')" :max="now()->format('Y-m-d\TH:i')" required />
                <x-select name="prescription_id" :label="__('مرتبط بدواء (اختياري)')" :options="$prescriptions->mapWithKeys(fn ($p) => [$p->id => $p->medication_name])->all()" :placeholder="__('غير محدد')" />
            </div>
            <div>
                <label for="files" class="label">{{ __('إرفاق ملفات (اختياري)') }}</label>
                <input id="files" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="input">
                <span class="hint">{{ __('حتى 3 ملفات (PDF أو صور)، بحد أقصى 5 ميجابايت لكل ملف.') }}</span>
                @error('files')<span class="error-text">{{ $message }}</span>@enderror
                @error('files.*')<span class="error-text">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="btn btn-primary">{{ __('إرسال') }}</button>
        </form>
    </section>

    <section class="mt-8" aria-labelledby="history-title">
        <h2 id="history-title" class="mb-3 text-xl">{{ __('سجل الأعراض') }}</h2>
        @forelse ($reports as $report)
            <article class="card mb-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold">{{ $report->occurred_at->translatedFormat('j F Y، h:i A') }}</p>
                    <p class="flex gap-2"><x-badge :status="$report->severity" /> <x-badge :status="$report->status" /></p>
                </div>
                <p class="mt-2 whitespace-pre-line">{{ $report->symptom }}</p>
                @if ($report->response_note)
                    <p class="mt-2 rounded-xl bg-sage-soft p-3"><strong>{{ __('رد الفريق') }}:</strong> {{ $report->response_note }}</p>
                @endif
                @foreach ($report->attachments as $file)
                    <a href="{{ route('attachments.show', $file) }}" class="mt-2 inline-block text-sm">📎 {{ $file->original_name }}</a>
                @endforeach
            </article>
        @empty
            <x-empty :title="__('لم تسجّل أي أعراض')" />
        @endforelse
        {{ $reports->links() }}
    </section>
</x-layouts.app>
