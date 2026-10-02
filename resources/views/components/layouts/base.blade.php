@props(['title' => null, 'description' => null, 'whatsapp' => true, 'raised' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <script>document.documentElement.classList.add('js')</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | ' : '' }}{{ __('اتزان') }}</title>
    <meta name="description" content="{{ $description ?? __('اتزان — رحلة إدارة الوزن بإشراف طبي، خطوة بخطوة.') }}">
    <meta name="theme-color" content="#16645B">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%2316645B'/%3E%3Cpath d='M6 21c5-9 9-9 12-4s6 4 8-4' stroke='white' stroke-width='3' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="skip-link">{{ __('تخطي إلى المحتوى') }}</a>

    {{ $slot }}

    @if ($whatsapp)
        <x-whatsapp :raised="$raised" />
    @endif
</body>
</html>
