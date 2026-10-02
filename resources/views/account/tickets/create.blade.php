@php use App\Enums\TicketType; @endphp
<x-layouts.app :title="__('طلب جديد')">
    <x-page-header :title="$type === TicketType::Complaint ? __('تقديم شكوى') : __('طلب دعم')" />

    <form method="POST" action="{{ route('account.tickets.store') }}" class="card max-w-2xl space-y-4" data-once novalidate>
        @csrf
        <x-select name="type" :label="__('النوع')" :options="TicketType::options()" :value="$type->value" required />
        <x-input name="subject" :label="__('الموضوع')" maxlength="150" required />
        <x-textarea name="body" :label="__('التفاصيل')" rows="6" required
                    :hint="__('لا تكتب معلومات صحية تفصيلية إن أمكن؛ سيتواصل معك الفريق المختص عند الحاجة.')" />
        <button type="submit" class="btn btn-primary">{{ __('إرسال') }}</button>
    </form>
</x-layouts.app>
