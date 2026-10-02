<?php

use App\Support\Money;

if (! function_exists('money')) {
    /** Formats integer halalas for display, e.g. 99900 => "999 ريال". */
    function money(int $halalas, bool $withCurrency = true): string
    {
        return Money::format($halalas, $withCurrency);
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(): bool
    {
        return app()->getLocale() === 'ar';
    }
}
