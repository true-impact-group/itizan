<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('formats')]
    public function test_formats_halalas_for_display(int $halalas, string $expected): void
    {
        $this->assertSame($expected, Money::format($halalas, false));
    }

    public static function formats(): array
    {
        return [
            'whole riyals have no decimals' => [99900, '999'],
            'thousands separator' => [149900, '1,499'],
            'halalas are kept' => [16650, '166.50'],
            'average annual' => [12492, '124.92'],
            'zero' => [0, '0'],
        ];
    }

    public function test_format_appends_the_currency_word(): void
    {
        $this->assertSame('999 ريال', Money::format(99900));

        app()->setLocale('en');
        $this->assertSame('999 SAR', Money::format(99900));
    }

    public function test_vat_is_extracted_from_a_vat_inclusive_price(): void
    {
        // 999 incl. 15% VAT => 999 * 15 / 115 = 130.30
        $this->assertSame(13030, Money::vatContained(99900));
        $this->assertSame(19552, Money::vatContained(149900));
    }

    public function test_vat_is_added_on_top_of_a_net_price(): void
    {
        $this->assertSame(15000, Money::vatOn(100000));
    }

    public function test_monthly_average_uses_the_exact_package_length(): void
    {
        $this->assertSame(16650, Money::perMonth(99900, 6));
        $this->assertSame(12492, Money::perMonth(149900, 12));
        $this->assertSame(500, Money::perMonth(500, 0), 'zero months must not divide by zero');
    }

    public function test_helper_function_matches_the_class(): void
    {
        $this->assertSame(Money::format(4500), money(4500));
    }
}
