<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalises a phone number to E.164. Saudi mobile numbers may be typed as
     * 05XXXXXXXX, 5XXXXXXXX, 9665XXXXXXXX, 00966... or +966...; Arabic-Indic digits are accepted.
     * Returns null when the input cannot be a valid number.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = strtr($input, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $hasPlus = str_starts_with(trim($digits), '+');
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        // Local Saudi formats.
        if (! $hasPlus) {
            if (preg_match('/^05\d{8}$/', $digits)) {
                return '+966'.substr($digits, 1);
            }
            if (preg_match('/^5\d{8}$/', $digits)) {
                return '+966'.$digits;
            }
        }

        // International: Saudi numbers must be mobile, others just need a plausible length.
        if (str_starts_with($digits, '966')) {
            return preg_match('/^9665\d{8}$/', $digits) ? '+'.$digits : null;
        }

        if (($hasPlus || strlen($digits) > 10) && preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return '+'.$digits;
        }

        return null;
    }

    /** Masks the middle of a number for display in lists, e.g. +966 5• ••• ••12. */
    public static function mask(?string $phone): string
    {
        if (! $phone || strlen($phone) < 6) {
            return (string) $phone;
        }

        return substr($phone, 0, 5).str_repeat('•', max(strlen($phone) - 7, 1)).substr($phone, -2);
    }
}
