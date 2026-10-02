@php use App\Enums\TicketStatus; @endphp
<x-layouts.app :title="$ticket->subject">
    <p class="mb-2"><a href="{{ route('account.tickets.index') }}">← {{ __('الدعم والشكاوى') }}</a></p>
    <x-page-header :title="$ticket->subject">
        <x-slot:actions><x-badge :status="$ticket->status" /></x-slot:actions>
    </x-page-header>

    <div class="space-y-4">
        <article class="card">
            <p class="text-sm text-ink-soft">{{ $ticket->type->label() }} · {{ $ticket->created_at->translatedFormat('j F Y، h:i A') }}</p>
            <p class="mt-2 whitespace-pre-line">{{ $ticket->body }}</p>
        </article>

        @foreach ($ticket->replies as $reply)
            <article class="{{ $reply->user_id === $ticket->user_id ? 'card' : 'card-sage' }}">
                <p class="text-sm text-ink-soft">{{ $reply->user_id === $ticket->user_id ? __('أنت') : __('فريق الدعم') }} · {{ $reply->created_at->translatedFormat('j F Y، h:i A') }}</p>
                <p class="mt-2 whitespace-pre-line">{{ $reply->body }}</p>
            </article>
        @endforeach
    </div>

    @if ($ticket->status !== TicketStatus::Closed)
        <form method="POST" action="{{ route('account.tickets.reply', $ticket) }}" class="card mt-6 space-y-4" data-once novalidate>
            @csrf
            <x-textarea name="body" :label="__('إضافة رد')" rows="4" required />
            <button type="submit" class="btn btn-primary">{{ __('إرسال الرد') }}</button>
        </form>
    @endif
</x-layouts.app>
