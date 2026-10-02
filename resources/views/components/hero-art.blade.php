{{-- Hero picture. Drop a licensed photograph at public/images/hero.jpg and it replaces this stand-in illustration automatically. --}}
@if (file_exists(public_path('images/hero.jpg')))
    <img src="{{ asset('images/hero.jpg') }}" alt="{{ __('شخص مبتسم في لحظة يومية إيجابية') }}" class="size-full object-cover" width="800" height="1000" fetchpriority="high">
@else
    <svg viewBox="0 0 400 500" preserveAspectRatio="xMidYMax slice" class="block size-full" role="img" aria-label="{{ __('رسم توضيحي لشخص مبتسم') }}">
        <circle cx="320" cy="90" r="70" fill="#fff" opacity=".35"/>
        <circle cx="60" cy="300" r="90" fill="#fff" opacity=".25"/>
        <path d="M60 500c0-95 60-150 140-150s140 55 140 150z" fill="#16645B"/>
        <path d="M118 262c-6-92 30-146 82-146s88 54 82 146c-2 30-12 56-26 76l30 70H114l30-70c-14-20-24-46-26-76z" fill="#0F4A43"/>
        <ellipse cx="200" cy="250" rx="50" ry="62" fill="#D9A07A"/>
        <circle cx="182" cy="248" r="4.5" fill="#20332F"/><circle cx="218" cy="248" r="4.5" fill="#20332F"/>
        <path d="M178 276q22 20 44 0" fill="none" stroke="#20332F" stroke-width="4.5" stroke-linecap="round"/>
        <circle cx="168" cy="266" r="9" fill="#E9A68F" opacity=".5"/><circle cx="232" cy="266" r="9" fill="#E9A68F" opacity=".5"/>
        <rect x="270" y="418" width="46" height="54" rx="10" fill="#fff"/><path d="M316 430q22 0 22 18t-22 18" fill="none" stroke="#fff" stroke-width="7"/>
    </svg>
@endif
