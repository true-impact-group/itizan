<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Supplements only. Prescription medications are added by administrators together with the pharmacy,
 * so no drug, brand or price is ever assumed here.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $supplements = [
            ['SUP-VITD', 'فيتامين د3', 'Vitamin D3', 4500],
            ['SUP-OMEGA', 'أوميغا 3', 'Omega-3', 6500],
            ['SUP-MULTI', 'فيتامينات متعددة', 'Multivitamin', 5500],
        ];

        foreach ($supplements as [$sku, $ar, $en, $price]) {
            Product::firstOrCreate(['sku' => $sku], [
                'name_ar' => $ar, 'name_en' => $en, 'type' => ProductType::Supplement,
                'requires_prescription' => false, 'price_halalas' => $price, 'in_stock' => true, 'is_active' => true,
            ]);
        }
    }
}
