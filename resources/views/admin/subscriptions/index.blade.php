@php use App\Enums\SubscriptionStatus; @endphp
<x-layouts.app :title="__('الاشتراكات')">
    <x-page-header :title="__('الاشتراكات')" />

    <form method="GET" class="mb-4 max-w-xs">
        <label for="status" class="label">{{ __('الحالة') }}</label>
        <select id="status" name="status" class="input" data-autosubmit>
            <option value="">{{ __('الكل') }}</option>
            @foreach (SubscriptionStatus::cases() as $option)<option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>@endforeach
        </select>
    </form>

    @if ($subscriptions->isEmpty())
        <x-empty :title="__('لا توجد اشتراكات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('الاشتراكات') }}</caption>
                <thead><tr><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('الباقة') }}</th><th scope="col">{{ __('الفترة') }}</th><th scope="col">{{ __('تجديد تلقائي') }}</th><th scope="col">{{ __('الحالة') }}</th></tr></thead>
                <tbody>
                @foreach ($subscriptions as $subscription)
                    <tr>
                        <td><a href="{{ route('admin.clients.show', $subscription->user) }}">{{ $subscription->user->name }}</a></td>
                        <td>{{ $subscription->package->name() }} · {{ money($subscription->price_halalas) }}</td>
                        <td>{{ $subscription->starts_at->translatedFormat('j M Y') }} — {{ $subscription->ends_at->translatedFormat('j M Y') }}</td>
                        <td>{{ $subscription->auto_renew ? __('نعم') : __('لا') }}</td>
                        <td><x-badge :status="$subscription->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $subscriptions->links() }}
    @endif
</x-layouts.app>
