<x-layouts.app :title="__('سجل التدقيق')">
    <x-page-header :title="__('سجل التدقيق')" :subtitle="__('من اطّلع على البيانات الحساسة أو غيّرها، ومتى. السجل للقراءة فقط ولا يتضمن محتوى طبيًا.')" />

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" role="search">
        <div class="min-w-64"><label for="action" class="label">{{ __('الإجراء (يبدأ بـ)') }}</label><input id="action" name="action" value="{{ $action }}" class="input" dir="ltr" placeholder="assessment."></div>
        <button type="submit" class="btn btn-primary">{{ __('تصفية') }}</button>
    </form>

    @if ($logs->isEmpty())
        <x-empty :title="__('لا توجد سجلات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('سجل التدقيق') }}</caption>
                <thead><tr><th scope="col">{{ __('الوقت') }}</th><th scope="col">{{ __('المستخدم') }}</th><th scope="col">{{ __('الإجراء') }}</th><th scope="col">{{ __('المرجع') }}</th><th scope="col">IP</th></tr></thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->translatedFormat('j M Y، h:i:s A') }}</td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        <td><bdi>{{ $log->action }}</bdi></td>
                        <td><bdi>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</bdi></td>
                        <td><bdi>{{ $log->ip_address }}</bdi></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</x-layouts.app>
