<x-layouts.app :title="__('الصفحات والسياسات')">
    <x-page-header :title="__('الصفحات والسياسات')" :subtitle="__('تُحرَّر بصيغة Markdown، وتُراجع النصوص القانونية والطبية قبل النشر.')" />

    <div class="table-wrap">
        <table class="table">
            <caption class="sr-only">{{ __('الصفحات والسياسات') }}</caption>
            <thead><tr><th scope="col">{{ __('العنوان') }}</th><th scope="col">{{ __('المعرّف') }}</th><th scope="col">{{ __('النشر') }}</th><th scope="col">{{ __('آخر تحديث') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
            <tbody>
            @foreach ($pages as $page)
                <tr>
                    <td class="font-semibold">{{ $page->title_ar }}</td>
                    <td><bdi>{{ $page->slug }}</bdi></td>
                    <td>@if ($page->is_published)<x-badge tone="success" :label="__('منشورة')" />@else<x-badge tone="neutral" :label="__('مخفية')" />@endif</td>
                    <td>{{ $page->updated_at->translatedFormat('j F Y') }}</td>
                    <td><a href="{{ route('admin.pages.edit', $page) }}">{{ __('تعديل') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
