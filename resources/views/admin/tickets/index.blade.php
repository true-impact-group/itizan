@php use App\Enums\TicketStatus; use App\Enums\TicketType; @endphp
<x-layouts.app :title="__('الدعم والشكاوى')">
    <x-page-header :title="__('الدعم والشكاوى')" />

    <form method="GET" class="mb-4 flex flex-wrap gap-4">
        <div class="min-w-48"><label for="status" class="label">{{ __('الحالة') }}</label>
            <select id="status" name="status" class="input" data-autosubmit>
                <option value="">{{ __('المفتوحة والجارية') }}</option>
                @foreach (TicketStatus::cases() as $option)<option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>@endforeach
            </select></div>
        <div class="min-w-48"><label for="type" class="label">{{ __('النوع') }}</label>
            <select id="type" name="type" class="input" data-autosubmit>
                <option value="">{{ __('الكل') }}</option>
                @foreach (TicketType::cases() as $option)<option value="{{ $option->value }}" @selected($type === $option)>{{ $option->label() }}</option>@endforeach
            </select></div>
    </form>

    @if ($tickets->isEmpty())
        <x-empty :title="__('لا توجد طلبات')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('الدعم والشكاوى') }}</caption>
                <thead><tr><th scope="col">{{ __('الموضوع') }}</th><th scope="col">{{ __('العميل') }}</th><th scope="col">{{ __('النوع') }}</th><th scope="col">{{ __('المتابع') }}</th><th scope="col">{{ __('التاريخ') }}</th><th scope="col">{{ __('الحالة') }}</th></tr></thead>
                <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="font-semibold">{{ $ticket->subject }}</a></td>
                        <td>{{ $ticket->user->name }}</td>
                        <td>{{ $ticket->type->label() }}</td>
                        <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                        <td>{{ $ticket->created_at->translatedFormat('j M Y') }}</td>
                        <td><x-badge :status="$ticket->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $tickets->links() }}
    @endif
</x-layouts.app>
