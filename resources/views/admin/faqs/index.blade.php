<x-layouts.app :title="__('الأسئلة الشائعة')">
    <x-page-header :title="__('الأسئلة الشائعة')">
        <x-slot:actions><a href="{{ route('admin.faqs.create') }}" class="btn btn-primary">{{ __('إضافة سؤال') }}</a></x-slot:actions>
    </x-page-header>

    @if ($faqs->isEmpty())
        <x-empty :title="__('لا توجد أسئلة')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('الأسئلة الشائعة') }}</caption>
                <thead><tr><th scope="col">{{ __('الترتيب') }}</th><th scope="col">{{ __('السؤال') }}</th><th scope="col">{{ __('النشر') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($faqs as $faq)
                    <tr>
                        <td>{{ $faq->sort_order }}</td>
                        <td class="font-semibold">{{ $faq->question_ar }}</td>
                        <td>@if ($faq->is_published)<x-badge tone="success" :label="__('منشور')" />@else<x-badge tone="neutral" :label="__('مخفي')" />@endif</td>
                        <td class="flex gap-2">
                            <a href="{{ route('admin.faqs.edit', $faq) }}">{{ __('تعديل') }}</a>
                            <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="{{ __('حذف هذا السؤال؟') }}">@csrf @method('DELETE')<button type="submit" class="text-danger underline">{{ __('حذف') }}</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
