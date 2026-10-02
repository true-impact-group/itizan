<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'pkg-'.fake()->unique()->numerify('####'),
            'name_ar' => 'الباقة النصف سنوية',
            'name_en' => 'Six-month package',
            'description_ar' => 'برنامج إدارة الوزن بإشراف طبي لمدة 6 أشهر.',
            'description_en' => 'Medically supervised weight management for 6 months.',
            'duration_months' => 6,
            'price_halalas' => 99900,
            'vat_inclusive' => true,
            'included_followups' => 3,
            'features_ar' => [],
            'features_en' => [],
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function annual(): static
    {
        return $this->state(fn () => [
            'name_ar' => 'الباقة السنوية',
            'name_en' => 'Annual package',
            'duration_months' => 12,
            'price_halalas' => 149900,
            'included_followups' => 6,
            'sort_order' => 2,
        ]);
    }
}
