@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('التنقل بين الصفحات') }}" class="no-print mt-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-soft">
            {{ __('عرض :from–:to من :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm" aria-disabled="true">{{ __('السابق') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary btn-sm">{{ __('السابق') }}</a>
            @endif

            <span class="px-3 text-sm font-medium">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary btn-sm">{{ __('التالي') }}</a>
            @else
                <span class="btn btn-secondary btn-sm" aria-disabled="true">{{ __('التالي') }}</span>
            @endif
        </div>
    </nav>
@endif
