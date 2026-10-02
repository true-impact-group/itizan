<?php

namespace Tests\Unit;

use App\Support\Bmi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BmiTest extends TestCase
{
    public function test_calculates_bmi_rounded_to_one_decimal(): void
    {
        $this->assertSame(33.0, Bmi::calculate(95.5, 170));
        $this->assertSame(22.9, Bmi::calculate(70, 175));
    }

    #[DataProvider('categories')]
    public function test_categories_follow_who_adult_ranges(float $bmi, string $expected): void
    {
        $this->assertSame($expected, Bmi::category($bmi));
    }

    public static function categories(): array
    {
        return [
            [17.0, 'نقص في الوزن'],
            [18.5, 'وزن طبيعي'],
            [24.9, 'وزن طبيعي'],
            [25.0, 'زيادة في الوزن'],
            [30.0, 'سمنة - الدرجة الأولى'],
            [35.0, 'سمنة - الدرجة الثانية'],
            [40.0, 'سمنة - الدرجة الثالثة'],
        ];
    }
}
