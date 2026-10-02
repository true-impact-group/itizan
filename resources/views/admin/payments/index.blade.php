@php use App\Enums\PaymentStatus; @endphp
<x-layouts.app :title="__('المدفوعات')">
    <x-page-header :title="__('المدفوعات')" />

    <form method="GET" class="mb-4 max-w-xs">
        <label for="status" class="label">{{ __('الحالة') }}</label>
        <select id="status" name="status" class="input" data-autosubmit>
            <option value="">{{ __('الكل') }}</option>
            @foreach (PaymentStatus::cases() as $option)<option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>@endforeach
        </select>
    </form>

    @if ($payments->isEmpty())
        <x-empty :title="__('لا توجد مدفوعات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('المدفوعات') }}</caption>
                <thead><tr><th scope="col">{{ __('رقم الإيصال') }}</th><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('الباقة') }}</th><th scope="col">{{ __('المبلغ') }}</th><th scope="col">{{ __('الوسيلة') }}</th><th scope="col">{{ __('الحالة') }}</th><th scope="col"><span class="sr-only">{{ __('إجراءات') }}</span></th></tr></thead>
                <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td><bdi>{{ $payment->invoice_number ?? '—' }}</bdi></td>
                        <td>{{ $payment->user->name }}</td>
                        <td>{{ $payment->subscription->package->name() }}</td>
                        <td>{{ money($payment->amount_halalas) }}</td>
                        <td>{{ $payment->method->label() }}</td>
                        <td><x-badge :status="$payment->status" /></td>
                        <td>
                            @can('refund', $payment)
                                @if ($payment->status === PaymentStatus::Paid)
                                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" data-confirm="{{ __('استرداد المبلغ كاملًا وإلغاء الاشتراك؟') }}" data-once>
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('استرداد') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    @endif
</x-layouts.app>
