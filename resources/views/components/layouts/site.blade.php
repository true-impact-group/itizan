@props(['title' => null, 'description' => null, 'flush' => false])
@php
    $nav = [
        ['how-it-works', 'كيف يعمل البرنامج'],
        ['services', 'الخدمات'],
        ['team', 'الفريق الطبي'],
        ['pricing', 'الأسعار والباقات'],
        ['faq', 'الأسئلة الشائعة'],
    ];
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
<x-layouts.base :title="$title" :description="$description" :raised="auth()->guest()">
    <header class="glass no-print sticky top-0 z-30 shadow-[0_8px_24px_-18px_rgba(32,51,47,.35)]">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="no-underline" aria-label="{{ __('اتزان — الصفحة الرئيسية') }}"><x-logo /></a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('التنقل الرئيسي') }}">
                @foreach ($nav as [$route, $label])
                    <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($route)) aria-current="page" @endif>{{ __($label) }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('locale', $otherLocale) }}" class="nav-link hidden sm:flex" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}">
                    {{ $otherLocale === 'en' ? 'English' : 'العربية' }}
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm">{{ __('حسابي') }}</a>
                @else
                    <a href="{{ route('login') }}" class="nav-link hidden sm:flex">{{ __('تسجيل الدخول') }}</a>
                    <a href="{{ route('free-consultation') }}" class="btn btn-primary btn-sm">{{ __('استشارة مجانية') }}</a>
                @endauth
                <button type="button" class="btn btn-secondary btn-sm lg:hidden" data-nav-toggle aria-expanded="false" aria-controls="mobile-menu">
                    <span class="sr-only">{{ __('القائمة') }}</span>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <nav id="mobile-menu" class="hidden border-t border-line bg-white px-4 py-3 lg:hidden" aria-label="{{ __('التنقل الرئيسي') }}">
            @foreach ($nav as [$route, $label])
                <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs($route)) aria-current="page" @endif>{{ __($label) }}</a>
            @endforeach
            <a href="{{ route('locale', $otherLocale) }}" class="nav-link" hreflang="{{ $otherLocale }}">{{ $otherLocale === 'en' ? 'English' : 'العربية' }}</a>
            @guest
                <a href="{{ route('login') }}" class="nav-link">{{ __('تسجيل الدخول') }}</a>
            @endguest
        </nav>
    </header>

    <main id="main" class="flex-1">
        @unless ($flush)
            <div class="mx-auto w-full max-w-6xl px-4 pt-6 sm:px-6"><x-flash /></div>
        @endunless
        {{ $slot }}
    </main>

    @guest
        {{-- Always-reachable first step on phones; sits above the content without hiding it (the footer has matching bottom padding). --}}
        <aside aria-label="{{ __('ابدأ باستشارة مجانية') }}" class="glass no-print fixed inset-x-0 bottom-0 z-30 flex gap-2 rounded-t-3xl px-4 pt-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom))] shadow-[0_-10px_30px_-18px_rgba(32,51,47,.4)] md:hidden">
            <a href="{{ route('free-consultation') }}" class="btn btn-primary flex-1">{{ __('ابدأ باستشارة مجانية') }}</a>
        </aside>
    @endguest

    <footer class="no-print mt-12 bg-petrol-dark text-white @guest pb-20 md:pb-0 @endguest">
        <div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3">
            <div>
                <x-logo light />
                <p class="mt-3 text-white/90">{{ __('اتزان — رحلة إدارة الوزن بإشراف طبي، خطوة بخطوة.') }}</p>
                <p class="mt-3 text-sm text-white/80">{{ __('الاستشارات تُقدَّم عبر منصة الطب الاتصالي FTMS.') }}</p>
            </div>
            <div>
                <h2 class="mb-3 text-lg text-white">{{ __('روابط مهمة') }}</h2>
                <ul class="space-y-1">
                    @foreach ([['about', 'من نحن'], ['contact', 'تواصل معنا'], ['prescription-journey', 'رحلة الوصفة والتوصيل'], ['policies.index', 'السياسات والشروط']] as [$route, $label])
                        <li><a href="{{ route($route) }}" class="text-white underline-offset-4 hover:underline">{{ __($label) }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="mb-3 text-lg text-white">{{ __('سلامتك أولًا') }}</h2>
                <p class="rounded-xl bg-white/10 p-3 text-white">
                    <strong>{{ __('اتزان ليست خدمة طوارئ.') }}</strong>
                    {{ __('في الحالات الطارئة اتصل بالهلال الأحمر على :number أو توجه إلى أقرب طوارئ.', ['number' => config('itizan.emergency_number')]) }}
                </p>
                <p class="mt-3 text-sm text-white/80">{{ __('المحتوى في الموقع للتوعية ولا يغني عن استشارة الطبيب. القرار الطبي للطبيب وحده.') }}</p>
            </div>
        </div>
        <div class="border-t border-white/15 px-4 py-4 text-center text-sm text-white/80">
            © {{ now()->year }} {{ __('اتزان') }}
        </div>
    </footer>
</x-layouts.base>
