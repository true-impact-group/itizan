@php use App\Enums\ContactRequestStatus; @endphp
<x-layouts.app :title="__('طلبات التواصل')">
    <x-page-header :title="__('طلبات التواصل')" />

    <form method="GET" class="mb-4 max-w-xs">
        <label for="status" class="label">{{ __('الحالة') }}</label>
        <select id="status" name="status" class="input" data-autosubmit>
            <option value="">{{ __('المفتوحة والجارية') }}</option>
            @foreach (ContactRequestStatus::cases() as $option)
                <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
            @endforeach
        </select>
    </form>

    @forelse ($requests as $contact)
        <article class="card mb-3 {{ $contact->isUrgent() ? 'border-2 border-danger' : '' }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold">
                    @if ($contact->isUrgent())<x-badge tone="danger" :label="__('عاجل')" />@endif
                    {{ $contact->user->name }} · {{ $contact->target->label() }}
                </p>
                <x-badge :status="$contact->status" />
            </div>
            <p class="text-sm text-ink-soft">{{ $contact->created_at->translatedFormat('j F Y، h:i A') }}@if ($contact->assignee) · {{ __('المتابع: :name', ['name' => $contact->assignee->name]) }}@endif</p>
            <p class="mt-2 whitespace-pre-line">{{ $contact->reason }}</p>

            @can('handle', $contact)
                <form method="POST" action="{{ route('care.contact-requests.update', $contact) }}" class="mt-4 grid gap-3 border-t border-line pt-4 md:grid-cols-3" data-once>
                    @csrf @method('PUT')
                    <x-select :name="'status'" :label="__('الحالة')" :options="ContactRequestStatus::options()" :value="$contact->status->value" :id="'status-'.$contact->id" />
                    <div class="md:col-span-2"><x-textarea name="response_note" :id="'note-'.$contact->id" :label="__('رد للعميل (يظهر في حسابه)')" rows="2" :value="$contact->response_note" /></div>
                    <div class="md:col-span-3"><button type="submit" class="btn btn-primary btn-sm">{{ __('حفظ') }}</button></div>
                </form>
            @endcan
        </article>
    @empty
        <x-empty :title="__('لا توجد طلبات')" />
    @endforelse
    {{ $requests->links() }}
</x-layouts.app>
