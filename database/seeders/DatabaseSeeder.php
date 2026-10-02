<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data needed in every environment. Demo accounts are NOT created here:
     * run `php artisan db:seed --class=DemoSeeder` locally if you want them.
     */
    public function run(): void
    {
        $this->call([
            PackageSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
