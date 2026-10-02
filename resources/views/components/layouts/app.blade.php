@props(['title' => null])
@php
    $user = auth()->user();
    $nav = \App\Support\Navigation::for($user->role);
    $unread = $user->unreadNotifications()->count();
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
<x-layouts.base :title="$title" :whatsapp="! $user->isStaff()">
    <header class="no-print border-b border-line bg-white">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route($user->role->homeRoute()) }}" class="no-underline" aria-label="{{ __('اتزان — الرئيسية') }}"><x-logo /></a>
            <div class="flex items-center gap-2">
                <a href="{{ route('notifications.index') }}" class="nav-link relative" aria-label="{{ __('الإشعارات') }}">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    @if ($unread > 0)
                        <span class="badge badge-danger !px-2 !py-0 text-xs">{{ $unread }}<span class="sr-only"> {{ __('إشعارات غير مقروءة') }}</span></span>
                    @endif
                </a>
                <a href="{{ route('locale', $otherLocale) }}" class="nav-link hidden sm:flex" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}">{{ $otherLocale === 'en' ? 'English' : 'العربية' }}</a>
                <span class="hidden text-sm text-ink-soft md:inline">{{ $user->name }} · {{ $user->role->label() }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('تسجيل الخروج') }}</button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 lg:flex-row">
        <aside class="no-print lg:w-64 lg:shrink-0">
            <button type="button" class="btn btn-secondary btn-block lg:hidden" data-nav-toggle aria-expanded="false" aria-controls="sidebar">
                {{ __('القائمة') }}
            </button>
            <nav id="sidebar" class="mt-3 hidden space-y-1 rounded-2xl border border-line bg-white p-3 lg:mt-0 lg:block" aria-label="{{ __('قائمة الحساب') }}">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" class="nav-link" @if (request()->routeIs($item['match'])) aria-current="page" @endif>{{ __($item['label']) }}</a>
                @endforeach
            </nav>
        </aside>

        <main id="main" class="min-w-0 flex-1">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
