@php use App\Enums\TicketType; @endphp
<x-layouts.app :title="__('الدعم والشكاوى')">
    <x-page-header :title="__('الدعم والشكاوى')" :subtitle="__('نتابع طلباتك وشكاواك حتى يتم حلها.')">
        <x-slot:actions>
            <a href="{{ route('account.tickets.create', ['type' => 'support']) }}" class="btn btn-primary">{{ __('طلب دعم') }}</a>
            <a href="{{ route('account.tickets.create', ['type' => 'complaint']) }}" class="btn btn-secondary">{{ __('تقديم شكوى') }}</a>
        </x-slot:actions>
    </x-page-header>

    @if ($tickets->isEmpty())
        <x-empty :title="__('لا توجد طلبات')" :text="__('إذا احتجت مساعدة فنحن هنا. يمكنك أيضًا التواصل عبر واتساب للاستفسارات العامة.')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('الدعم والشكاوى') }}</caption>
                <thead><tr><th scope="col">{{ __('الموضوع') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('التاريخ') }}</th><th scope="col">{{ __('الحالة') }}</th></tr></thead>
                <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td><a href="{{ route('account.tickets.show', $ticket) }}" class="font-semibold">{{ $ticket->subject }}</a></td>
                        <td>{{ $ticket->type->label() }}</td>
                        <td>{{ $ticket->created_at->translatedFormat('j F Y') }}</td>
                        <td><x-badge :status="$ticket->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $tickets->links() }}
    @endif
</x-layouts.app>
