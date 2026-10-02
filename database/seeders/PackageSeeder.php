<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * The two paid packages. Follow-up counts (3 and 6) are the initial proposal and must be approved
     * after checking delivery cost and team capacity; admins can change them in the dashboard.
     */
    public function run(): void
    {
        Package::updateOrCreate(['slug' => 'six-months'], [
            'name_ar' => 'الباقة النصف سنوية',
            'name_en' => 'Six-month package',
            'description_ar' => 'برنامج إدارة الوزن بإشراف طبي لمدة 6 أشهر.',
            'description_en' => 'Medically supervised weight management for 6 months.',
            'duration_months' => 6,
            'price_halalas' => 99900,
            'vat_inclusive' => true,
            'included_followups' => 3,
            'features_ar' => ['متابعة الخطة والقياسات في حساب واحد', 'تنبيهات الجرعات وتسجيل الأعراض'],
            'features_en' => ['Plan and measurements in one account', 'Dose reminders and symptom logging'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Package::updateOrCreate(['slug' => 'annual'], [
            'name_ar' => 'الباقة السنوية',
            'name_en' => 'Annual package',
            'description_ar' => 'برنامج إدارة الوزن بإشراف طبي لمدة سنة، بقيمة أفضل.',
            'description_en' => 'Medically supervised weight management for a year, at a better value.',
            'duration_months' => 12,
            'price_halalas' => 149900,
            'vat_inclusive' => true,
            'included_followups' => 6,
            'features_ar' => ['متابعة الخطة والقياسات في حساب واحد', 'تنبيهات الجرعات وتسجيل الأعراض'],
            'features_en' => ['Plan and measurements in one account', 'Dose reminders and symptom logging'],
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
