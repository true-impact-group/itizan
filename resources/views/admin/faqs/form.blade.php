<x-layouts.app :title="$faq ? __('تعديل سؤال') : __('إضافة سؤال')">
    <p class="mb-2"><a href="{{ route('admin.faqs.index') }}">← {{ __('الأسئلة الشائعة') }}</a></p>
    <x-page-header :title="$faq ? __('تعديل سؤال') : __('إضافة سؤال')" />

    <form method="POST" action="{{ $faq ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="card max-w-3xl space-y-5" data-once novalidate>
        @csrf
        @if ($faq) @method('PUT') @endif
        <x-input name="question_ar" :label="__('السؤال (عربي)')" :value="$faq?->question_ar" required />
        <x-textarea name="answer_ar" :label="__('الجواب (عربي)')" rows="4" :value="$faq?->answer_ar" required />
        <x-input name="question_en" :label="__('السؤال (إنجليزي)')" :value="$faq?->question_en" required dir="ltr" />
        <x-textarea name="answer_en" :label="__('الجواب (إنجليزي)')" rows="4" :value="$faq?->answer_en" required dir="ltr" />
        <x-input name="sort_order" type="number" min="0" :label="__('الترتيب')" :value="$faq?->sort_order ?? 0" />
        <x-checkbox name="is_published" :label="__('منشور')" :checked="$faq ? $faq->is_published : true" />
        <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
    </form>
</x-layouts.app>
