<?php

namespace Database\Seeders;

use App\Enums\AssessmentStatus;
use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Models\Measurement;
use App\Models\MedicalAssessment;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WeightPlan;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/staging demo data: one account per role (password "password1"), a verified 24-hour doctor roster,
 * and a demo client with a subscription, an approved assessment, a plan, measurements and a prescription.
 * Refuses to run in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $this->call(DatabaseSeeder::class);

        $pharmacy = Pharmacy::firstOrCreate(['license_number' => 'PH-DEMO-001'], ['name' => 'صيدلية اتزان التجريبية', 'city' => 'الرياض', 'phone' => '0111111111', 'is_active' => true]);

        $this->staff('+966500000001', 'مدير النظام', Role::Admin);
        $this->staff('+966500000002', 'موظف الدعم', Role::Support);
        $this->staff('+966500000003', 'فريق الرعاية', Role::CareTeam);
        $this->staff('+966500000004', 'موظف الصيدلية', Role::Pharmacy, $pharmacy->id);
        $this->staff('+966500000005', 'مندوب التوصيل', Role::Delivery);

        $dietitian = $this->practitioner('+966500000006', 'سارة العتيبي', 'Sara Alotaibi', PractitionerType::Dietitian, 'أخصائية تغذية علاجية', 'Clinical Dietitian', 'SCFHS-DEMO-D1');
        $doctorDay = $this->practitioner('+966500000007', 'د. خالد الحربي', 'Dr. Khalid Alharbi', PractitionerType::Doctor, 'استشاري غدد صماء وسمنة', 'Endocrinology & Obesity Consultant', 'SCFHS-DEMO-1', instant: true);
        $doctorNight = $this->practitioner('+966500000008', 'د. نورة القحطاني', 'Dr. Noura Alqahtani', PractitionerType::Doctor, 'استشارية سمنة وتغذية علاجية', 'Obesity & Clinical Nutrition Consultant', 'SCFHS-DEMO-2');

        // Together the two doctors cover the whole day, every day.
        foreach (range(0, 6) as $weekday) {
            $doctorDay->availabilities()->updateOrCreate(['weekday' => $weekday, 'start_time' => '07:00'], ['end_time' => '19:00', 'slot_minutes' => 20]);
            $doctorNight->availabilities()->updateOrCreate(['weekday' => $weekday, 'start_time' => '19:00'], ['end_time' => '23:59', 'slot_minutes' => 20]);
            $doctorNight->availabilities()->updateOrCreate(['weekday' => $weekday, 'start_time' => '00:00'], ['end_time' => '07:00', 'slot_minutes' => 20]);
        }

        Product::firstOrCreate(['sku' => 'MED-DEMO'], [
            'name_ar' => 'دواء تجريبي (وصفة)', 'name_en' => 'Demo prescription medication', 'type' => 'medication',
            'requires_prescription' => true, 'price_halalas' => 45000, 'in_stock' => true, 'is_active' => true,
        ]);

        $this->demoClient($doctorDay, $dietitian);

        $this->command?->info('Demo accounts created. Password for all: password1');
        $this->command?->table(['Role', 'Phone'], [
            ['admin', '+966500000001'], ['support', '+966500000002'], ['care team', '+966500000003'],
            ['pharmacy', '+966500000004'], ['delivery', '+966500000005'], ['dietitian', '+966500000006'],
            ['doctor', '+966500000007'], ['doctor (night)', '+966500000008'], ['client', '+966500000010'],
        ]);
    }

    private function staff(string $phone, string $name, Role $role, ?int $pharmacyId = null): User
    {
        $user = User::firstOrNew(['phone' => $phone]);
        $user->fill(['name' => $name, 'password' => 'password1', 'locale' => 'ar']);
        $user->role = $role;
        $user->pharmacy_id = $pharmacyId;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function practitioner(string $phone, string $name, string $nameEn, PractitionerType $type, string $specAr, string $specEn, string $license, bool $instant = false): Practitioner
    {
        $user = $this->staff($phone, $name, $type === PractitionerType::Doctor ? Role::Doctor : Role::Dietitian);

        return Practitioner::updateOrCreate(['user_id' => $user->id], [
            'type' => $type, 'name_en' => $nameEn, 'specialty_ar' => $specAr, 'specialty_en' => $specEn,
            'license_number' => $license, 'license_authority' => 'SCFHS',
            'license_verified_at' => now(), 'license_expires_on' => now()->addYear()->toDateString(),
            'bio_ar' => 'خبرة في إدارة الوزن والسمنة بإشراف طبي.', 'bio_en' => 'Experienced in medically supervised weight and obesity management.',
            'years_experience' => 12, 'accepts_instant' => $instant, 'is_active' => true,
        ]);
    }

    private function demoClient(Practitioner $doctor, Practitioner $dietitian): void
    {
        $client = User::firstOrNew(['phone' => '+966500000010']);
        $client->fill(['name' => 'عميل تجريبي', 'email' => 'client@itizan.test', 'password' => 'password1', 'gender' => 'male', 'birth_date' => '1990-05-10', 'city' => 'الرياض', 'locale' => 'ar']);
        $client->role = Role::Client;
        $client->is_active = true;
        $client->save();

        if ($client->subscriptions()->exists()) {
            return;
        }

        $package = Package::where('slug', 'six-months')->firstOrFail();
        $subscription = Subscription::create([
            'user_id' => $client->id, 'package_id' => $package->id, 'status' => 'active',
            'starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addMonths($package->duration_months),
            'payment_method' => 'mada', 'price_halalas' => $package->price_halalas, 'followups_included' => $package->included_followups,
        ]);
        Payment::create([
            'user_id' => $client->id, 'subscription_id' => $subscription->id, 'amount_halalas' => $package->price_halalas,
            'vat_halalas' => $package->vatHalalas(), 'method' => 'mada', 'status' => 'paid', 'provider' => 'sandbox',
            'provider_reference' => 'sbx_demo_'.$client->id, 'paid_at' => now()->subMonth(), 'invoice_number' => 'ITZ-'.now()->format('Y').'-900001',
        ]);

        $assessment = MedicalAssessment::create([
            'user_id' => $client->id, 'status' => AssessmentStatus::Approved, 'height_cm' => 175, 'weight_kg' => 98.0, 'waist_cm' => 108, 'bmi' => 32.0,
            'target_weight_kg' => 82, 'medical_history' => 'لا يوجد', 'chronic_conditions' => 'لا يوجد', 'current_medications' => 'لا يوجد',
            'allergies' => 'لا يوجد', 'goals' => 'خسارة وزن صحية ومستدامة',
            'safety_answers' => array_fill_keys(array_keys(config('itizan.safety_questions')), 'no'),
            'submitted_at' => now()->subWeeks(3), 'reviewed_by' => $doctor->user_id, 'reviewed_at' => now()->subWeeks(3),
        ]);

        WeightPlan::create([
            'user_id' => $client->id, 'assessment_id' => $assessment->id, 'practitioner_id' => $doctor->id, 'dietitian_id' => $dietitian->id,
            'status' => 'active', 'start_weight_kg' => 98.0, 'target_weight_kg' => 82.0, 'start_waist_cm' => 108, 'target_waist_cm' => 95,
            'doctor_notes' => 'نبدأ بخطوات صغيرة ومستمرة. كل خطوة في المتابعة تساعدنا على دعمك.',
            'next_steps' => ['سجّل وزنك مرة في الأسبوع', 'أكمل التحليل المطلوب', 'اشرب الماء بانتظام'],
            'nutrition_guidance' => 'وجبات متوازنة بانتظام، مع التركيز على البروتين والخضروات.', 'starts_on' => now()->subWeeks(3)->toDateString(),
        ]);

        foreach ([[21, 98.0, 108], [14, 96.8, 107], [7, 95.9, 106], [0, 95.2, 105]] as [$daysAgo, $weight, $waist]) {
            Measurement::create(['user_id' => $client->id, 'measured_on' => now()->subDays($daysAgo)->toDateString(), 'weight_kg' => $weight, 'waist_cm' => $waist, 'source' => 'client']);
        }

        Prescription::create([
            'user_id' => $client->id, 'practitioner_id' => $doctor->id, 'medication_name' => 'دواء تجريبي', 'dose' => '0.25 ملغ',
            'instructions' => 'تعليمات تجريبية فقط.', 'interval_days' => 7, 'dose_time' => '09:00', 'starts_on' => now()->subWeeks(2)->toDateString(),
            'status' => 'active', 'next_dose_at' => now()->addDay()->setTime(9, 0),
            'pre_dose_questions' => [['text' => 'هل تشعر بغثيان شديد أو قيء مستمر؟', 'alert_on' => 'yes']],
            'product_id' => Product::where('sku', 'MED-DEMO')->value('id'),
        ]);
    }
}
