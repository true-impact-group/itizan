<x-layouts.app :title="__('تعديل صفحة')">
    <p class="mb-2"><a href="{{ route('admin.pages.index') }}">← {{ __('الصفحات والسياسات') }}</a></p>
    <x-page-header :title="$page->title_ar" />

    <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="card max-w-4xl space-y-5" data-once novalidate>
        @csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="title_ar" :label="__('العنوان (عربي)')" :value="$page->title_ar" required />
            <x-input name="title_en" :label="__('العنوان (إنجليزي)')" :value="$page->title_en" required dir="ltr" />
        </div>
        <x-textarea name="body_ar" :label="__('النص (عربي) — Markdown')" rows="16" :value="$page->body_ar" required
                    :hint="__('يُسمح بالعناوين (##) والقوائم (-) والروابط. يُزال أي HTML خام.')" />
        <x-textarea name="body_en" :label="__('النص (إنجليزي) — Markdown')" rows="16" :value="$page->body_en" required dir="ltr" />
        <x-checkbox name="is_published" :label="__('منشورة')" :checked="$page->is_published" />
        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
            <a href="{{ route('policies.show', $page) }}" class="btn btn-secondary" target="_blank" rel="noopener">{{ __('معاينة') }}</a>
        </div>
    </form>
</x-layouts.app>
