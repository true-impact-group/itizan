@php use App\Enums\ContactTarget; @endphp
<x-layouts.app :title="__('التواصل مع الفريق')">
    <x-page-header :title="__('التواصل مع الطبيب أو فريق الرعاية')" :subtitle="__('أرسل طلبك من هنا. للمعلومات الصحية الحساسة استخدم حسابك وليس واتساب.')" />

    <x-emergency class="mb-6" />

    <section class="card" aria-labelledby="new-title">
        <h2 id="new-title" class="text-xl">{{ __('طلب تواصل جديد') }}</h2>
        <form method="POST" action="{{ route('account.contact-requests.store') }}" class="mt-4 space-y-4" data-once novalidate>
            @csrf
            <x-select name="target" :label="__('مع من تريد التواصل؟')" :options="ContactTarget::options()" value="care_team" required />
            <x-textarea name="reason" :label="__('ما الذي تحتاجه؟')" rows="4" required />
            <button type="submit" class="btn btn-primary">{{ __('إرسال الطلب') }}</button>
        </form>
    </section>

    <section class="mt-8" aria-labelledby="history-title">
        <h2 id="history-title" class="mb-3 text-xl">{{ __('طلباتك') }}</h2>
        @forelse ($requests as $contact)
            <article class="card mb-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold">{{ $contact->target->label() }} · {{ $contact->created_at->translatedFormat('j F Y') }}</p>
                    <x-badge :status="$contact->status" />
                </div>
                <p class="mt-2 whitespace-pre-line">{{ $contact->reason }}</p>
                @if ($contact->response_note)
                    <p class="mt-2 rounded-xl bg-sage-soft p-3"><strong>{{ __('رد الفريق') }}:</strong> {{ $contact->response_note }}</p>
                @endif
            </article>
        @empty
            <x-empty :title="__('لا توجد طلبات')" />
        @endforelse
        {{ $requests->links() }}
    </section>
</x-layouts.app>
