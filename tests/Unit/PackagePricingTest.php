<?php

namespace Tests\Unit;

use App\Models\Package;
use App\Services\PackageComparison;
use Database\Seeders\PackageSeeder;
use Tests\TestCase;

/** The pricing promises from the brief, checked against the seeded packages. */
class PackagePricingTest extends TestCase
{
    private Package $sixMonths;

    private Package $annual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PackageSeeder::class);
        $this->sixMonths = Package::where('slug', 'six-months')->firstOrFail();
        $this->annual = Package::where('slug', 'annual')->firstOrFail();
    }

    public function test_prices_are_999_and_1499_riyals(): void
    {
        $this->assertSame(99900, $this->sixMonths->price_halalas);
        $this->assertSame(149900, $this->annual->price_halalas);
    }

    public function test_monthly_averages_match_the_brief(): void
    {
        $this->assertSame('166.50', money($this->sixMonths->monthlyAverageHalalas(), false));
        $this->assertSame('124.92', money($this->annual->monthlyAverageHalalas(), false));
    }

    public function test_four_payment_instalments_match_the_brief(): void
    {
        $this->assertSame('249.75', money($this->sixMonths->installmentHalalas(), false));
        $this->assertSame('374.75', money($this->annual->installmentHalalas(), false));
    }

    public function test_annual_package_saves_499_riyals_about_25_percent(): void
    {
        $savings = (new PackageComparison)->longestPackageSavings(Package::all());

        $this->assertNotNull($savings);
        $this->assertTrue($savings['package']->is($this->annual));
        $this->assertSame(49900, $savings['halalas']);
        $this->assertSame(25, $savings['percent']);
    }

    public function test_no_saving_is_shown_when_there_is_nothing_to_compare(): void
    {
        $this->assertNull((new PackageComparison)->longestPackageSavings(collect([$this->sixMonths])));
    }

    public function test_vat_inclusive_price_is_not_charged_twice(): void
    {
        $this->assertTrue($this->sixMonths->vat_inclusive);
        $this->assertSame(99900, $this->sixMonths->chargeHalalas());
        $this->assertSame(13030, $this->sixMonths->vatHalalas());
    }

    public function test_vat_exclusive_price_adds_vat_to_the_charge(): void
    {
        $package = Package::factory()->create(['price_halalas' => 100000, 'vat_inclusive' => false]);

        $this->assertSame(15000, $package->vatHalalas());
        $this->assertSame(115000, $package->chargeHalalas());
    }

    public function test_proposed_follow_up_counts_are_three_and_six(): void
    {
        $this->assertSame(3, $this->sixMonths->included_followups);
        $this->assertSame(6, $this->annual->included_followups);
    }
}
