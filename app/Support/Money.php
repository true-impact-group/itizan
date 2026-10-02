<?php

namespace App\Support;

/**
 * All money is stored as integer halalas (1 SAR = 100 halalas) to avoid float errors.
 */
final class Money
{
    public static function format(int $halalas, bool $withCurrency = true): string
    {
        $riyals = $halalas / 100;
        $number = $halalas % 100 === 0
            ? number_format($riyals, 0, '.', ',')
            : number_format($riyals, 2, '.', ',');

        return $withCurrency ? $number.' '.__('ريال') : $number;
    }

    /** The VAT portion contained inside a VAT-inclusive gross amount. */
    public static function vatContained(int $grossHalalas, ?float $ratePercent = null): int
    {
        $rate = $ratePercent ?? (float) config('itizan.vat_rate');

        return (int) round($grossHalalas * $rate / (100 + $rate));
    }

    /** The VAT to add on top of a net amount. */
    public static function vatOn(int $netHalalas, ?float $ratePercent = null): int
    {
        $rate = $ratePercent ?? (float) config('itizan.vat_rate');

        return (int) round($netHalalas * $rate / 100);
    }

    /** Average per month, rounded to the nearest halala. */
    public static function perMonth(int $halalas, int $months): int
    {
        return $months > 0 ? (int) round($halalas / $months) : $halalas;
    }
}
