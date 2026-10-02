<?php

namespace App\Support;

final class Bmi
{
    public static function calculate(float $weightKg, float $heightCm): float
    {
        $meters = $heightCm / 100;

        return round($weightKg / ($meters * $meters), 1);
    }

    /** WHO adult categories. Shown as information only; the doctor interprets the result. */
    public static function category(float $bmi): string
    {
        return match (true) {
            $bmi < 18.5 => 'نقص في الوزن',
            $bmi < 25 => 'وزن طبيعي',
            $bmi < 30 => 'زيادة في الوزن',
            $bmi < 35 => 'سمنة - الدرجة الأولى',
            $bmi < 40 => 'سمنة - الدرجة الثانية',
            default => 'سمنة - الدرجة الثالثة',
        };
    }
}
