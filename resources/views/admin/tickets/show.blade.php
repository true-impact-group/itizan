@php use App\Enums\TicketStatus; @endphp
<x-layouts.app :title="$ticket->subject">
    <p class="mb-2"><a href="{{ route('admin.tickets.index') }}">← {{ __('الدعم والشكاوى') }}</a></p>
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->user->name.' · '.$ticket->type->label()">
        <x-slot:actions><x-badge :status="$ticket->status" /></x-slot:actions>
    </x-page-header>

    <div class="space-y-4">
        <article class="card"><p class="text-sm text-ink-soft">{{ $ticket->created_at->translatedFormat('j F Y، h:i A') }}</p><p class="mt-2 whitespace-pre-line">{{ $ticket->body }}</p></article>
        @foreach ($ticket->replies as $reply)
            <article class="{{ $reply->is_internal ? 'card-peach' : ($reply->user_id === $ticket->user_id ? 'card' : 'card-sage') }}">
                <p class="text-sm text-ink-soft">
                    {{ $reply->author->name }} · {{ $reply->created_at->translatedFormat('j F Y، h:i A') }}
                    @if ($reply->is_internal)<x-badge tone="warning" :label="__('ملاحظة داخلية')" />@endif
                </p>
                <p class="mt-2 whitespace-pre-line">{{ $reply->body }}</p>
            </article>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" class="card space-y-4 lg:col-span-2" data-once novalidate>
            @csrf
            <x-textarea name="body" :label="__('إضافة رد')" rows="4" required />
            <x-checkbox name="internal" :label="__('ملاحظة داخلية (لا تظهر للعميل)')" />
            <button type="submit" class="btn btn-primary">{{ __('إرسال') }}</button>
        </form>

        <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="card space-y-4" data-once>
            @csrf @method('PUT')
            <x-select name="status" :label="__('الحالة')" :options="TicketStatus::options()" :value="$ticket->status->value" />
            <button type="submit" class="btn btn-secondary">{{ __('تحديث الحالة') }}</button>
        </form>
    </div>
</x-layouts.app>
