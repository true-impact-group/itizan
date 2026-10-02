@props(['raised' => false])
{{-- Opens WhatsApp with the support message already typed; the client only presses "send". --}}
<aside aria-label="{{ __('واتساب') }}" class="no-print">
    <a href="{{ \App\Support\Whatsapp::url() }}" target="_blank" rel="noopener noreferrer"
       class="fixed {{ $raised ? 'bottom-24 md:bottom-5' : 'bottom-5' }} z-40 inline-flex min-h-14 items-center gap-2 rounded-full bg-petrol px-5 py-3 font-semibold text-white no-underline shadow-lg hover:bg-petrol-dark start-5"
       aria-label="{{ __('تواصل معنا عبر واتساب') }}">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5A8.5 8.5 0 1 1 21 11.5Z"/>
            <path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1.2-1.4-2-1-1 .7a4 4 0 0 1-1.9-1.9l.7-1-1-2L9 9.5Z" fill="currentColor" stroke="none"/>
        </svg>
        <span class="hidden sm:inline">{{ __('واتساب') }}</span>
    </a>
</aside>
