<?php

namespace App\Actions\Plans;

use App\Enums\AssessmentStatus;
use App\Enums\PlanStatus;
use App\Enums\PractitionerType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\MedicalAssessment;
use App\Models\Practitioner;
use App\Models\User;
use App\Models\WeightPlan;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class CreateWeightPlan
{
    /**
     * @param  array{target_weight_kg: float, target_waist_cm?: ?float, doctor_notes?: ?string, next_steps?: list<string>, review_on?: mixed, dietitian_id?: ?int}  $data
     */
    public function handle(User $client, Practitioner $doctor, array $data): WeightPlan
    {
        if ($doctor->type !== PractitionerType::Doctor || ! $doctor->isVerified()) {
            throw new BusinessRuleViolation(__('فقط الطبيب الموثّق يمكنه إعداد الخطة.'));
        }

        $plan = DB::transaction(function () use ($client, $doctor, $data) {
            $assessment = MedicalAssessment::query()
                ->where('user_id', $client->id)
                ->where('status', AssessmentStatus::Approved->value)
                ->latest('reviewed_at')
                ->first();

            if ($assessment === null) {
                throw new BusinessRuleViolation(__('اعتمد التقييم الطبي للعميل أولًا قبل إعداد الخطة.'));
            }

            if (! empty($data['dietitian_id'])) {
                $isDietitian = Practitioner::query()->verified()->ofType(PractitionerType::Dietitian)->whereKey($data['dietitian_id'])->exists();
                if (! $isDietitian) {
                    throw new BusinessRuleViolation(__('أخصائي التغذية المختار غير متاح.'));
                }
            }

            // One active plan per client: the previous one is closed, not deleted.
            WeightPlan::query()->where('user_id', $client->id)->where('status', PlanStatus::Active->value)
                ->update(['status' => PlanStatus::Completed->value]);

            $startWeight = $client->measurements()->latest('measured_on')->latest('id')->value('weight_kg') ?? $assessment->weight_kg;

            return WeightPlan::create([
                'user_id' => $client->id,
                'assessment_id' => $assessment->id,
                'practitioner_id' => $doctor->id,
                'dietitian_id' => $data['dietitian_id'] ?? null,
                'status' => PlanStatus::Active,
                'start_weight_kg' => $startWeight,
                'target_weight_kg' => $data['target_weight_kg'],
                'start_waist_cm' => $assessment->waist_cm,
                'target_waist_cm' => $data['target_waist_cm'] ?? null,
                'doctor_notes' => $data['doctor_notes'] ?? null,
                'next_steps' => array_values(array_filter($data['next_steps'] ?? [])),
                'starts_on' => today(),
                'review_on' => $data['review_on'] ?? null,
            ]);
        });

        Audit::log('plan.created', $plan);
        $client->notify(new GenericNotification(
            'تم إعداد خطة إدارة الوزن الخاصة بك',
            'اطّلع على خطتك وخطواتك القادمة من حسابك.',
            route('account.plan.show'),
        ));

        return $plan;
    }
}
