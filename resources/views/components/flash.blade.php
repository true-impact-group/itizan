{{-- Session messages and a validation summary. Each is announced to screen readers. --}}
@if (session('status'))
    <div role="status" class="alert alert-success mb-4 flex items-start justify-between gap-3">
        <span>{{ session('status') }}</span>
        <button type="button" data-dismiss class="shrink-0 font-bold" aria-label="{{ __('إغلاق') }}">×</button>
    </div>
@endif
@if (session('warning'))
    <div role="alert" class="alert alert-warning mb-4">{{ session('warning') }}</div>
@endif
@if (session('error'))
    <div role="alert" class="alert alert-danger mb-4">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="alert alert-danger mb-4">
        <p class="font-bold">{{ __('تحتاج بعض الحقول إلى مراجعة:') }}</p>
        <ul class="mt-1 list-disc ps-6">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
