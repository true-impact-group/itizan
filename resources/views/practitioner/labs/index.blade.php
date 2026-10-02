<x-layouts.app :title="__('نتائج التحاليل')">
    <x-page-header :title="__('نتائج تحاليل بانتظار المراجعة')" />

    @if ($labs->isEmpty())
        <x-empty :title="__('لا توجد نتائج بانتظار المراجعة')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('نتائج التحاليل') }}</caption>
                <thead><tr><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('التحليل') }}</th><th scope="col">{{ __('رُفعت') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($labs as $lab)
                    <tr>
                        <td class="font-semibold">{{ $lab->user->name }}</td>
                        <td>{{ $lab->name }}</td>
                        <td>{{ $lab->result_uploaded_at?->diffForHumans() }}</td>
                        <td><a href="{{ route('practitioner.clients.show', $lab->user_id) }}">{{ __('فتح الملف') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $labs->links() }}
    @endif
</x-layouts.app>
