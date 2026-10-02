<x-layouts.app :title="__('الإشعارات')">
    <x-page-header :title="__('الإشعارات')">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">{{ __('تحديد الكل كمقروء') }}</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    @forelse ($notifications as $notification)
        <article class="card mb-3 {{ $notification->read_at ? '' : 'border-petrol' }}">
            <p class="font-semibold">
                @unless ($notification->read_at)<span class="badge badge-info me-2">{{ __('جديد') }}</span>@endunless
                {{ $notification->data['title'] ?? '' }}
            </p>
            <p class="mt-1">{{ $notification->data['body'] ?? '' }}</p>
            <p class="mt-1 text-sm text-ink-soft">
                {{ $notification->created_at->diffForHumans() }}
                @if ($notification->data['url'] ?? null) · <a href="{{ $notification->data['url'] }}">{{ __('فتح') }}</a>@endif
            </p>
        </article>
    @empty
        <x-empty :title="__('لا توجد إشعارات')" />
    @endforelse
    {{ $notifications->links() }}
</x-layouts.app>
