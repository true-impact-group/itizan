@php use App\Enums\LabStatus; @endphp
<x-layouts.app :title="__('التحاليل')">
    <x-page-header :title="__('التحاليل')" :subtitle="__('ترى هنا التحاليل التي يطلبها الطبيب، وترفع نتائجها وتتابع مراجعتها.')" />

    @forelse ($labs as $lab)
        <article class="card mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl">{{ $lab->name }}</h2>
                    <p class="text-sm text-ink-soft">{{ __('طلبه :name', ['name' => $lab->practitioner->displayName()]) }}@if ($lab->due_on) · {{ __('المطلوب قبل :date', ['date' => $lab->due_on->translatedFormat('j F Y')]) }}@endif</p>
                </div>
                <x-badge :status="$lab->status" />
            </div>
            @if ($lab->instructions)<p class="mt-2">{{ $lab->instructions }}</p>@endif

            @foreach ($lab->attachments as $file)
                <a href="{{ route('attachments.show', $file) }}" class="mt-2 block text-sm">📎 {{ $file->original_name }}</a>
            @endforeach

            @if ($lab->status === LabStatus::Reviewed && $lab->reviewer_notes)
                <p class="mt-3 rounded-xl bg-sage-soft p-3"><strong>{{ __('ملاحظات الطبيب') }}:</strong> {{ $lab->reviewer_notes }}</p>
            @endif

            @can('upload', $lab)
                <form method="POST" action="{{ route('account.labs.upload', $lab) }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-line pt-4" data-once>
                    @csrf
                    <label for="files-{{ $lab->id }}" class="label">{{ $lab->status === LabStatus::Requested ? __('ارفع النتيجة') : __('ارفع ملفات إضافية') }}</label>
                    <input id="files-{{ $lab->id }}" type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png" class="input">
                    <span class="hint">{{ __('حتى 5 ملفات (PDF أو صور)، بحد أقصى 5 ميجابايت لكل ملف.') }}</span>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('رفع') }}</button>
                </form>
            @endcan
        </article>
    @empty
        <x-empty :title="__('لا توجد تحاليل مطلوبة')" :text="__('عندما يطلب الطبيب تحليلًا ستجده هنا.')" />
    @endforelse
    {{ $labs->links() }}
</x-layouts.app>
