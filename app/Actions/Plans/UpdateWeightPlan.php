<?php

namespace App\Actions\Plans;

use App\Enums\PlanStatus;
use App\Enums\PractitionerType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Practitioner;
use App\Models\WeightPlan;
use App\Support\Audit;
use Illuminate\Support\Arr;

class UpdateWeightPlan
{
    /** The doctor edits the medical parts of the plan; the dietitian edits only the nutrition guidance. */
    public function handle(WeightPlan $plan, Practitioner $by, array $data): WeightPlan
    {
        if (! $by->isVerified()) {
            throw new BusinessRuleViolation(__('لا يمكنك تعديل الخطة.'));
        }

        if ($by->type === PractitionerType::Dietitian) {
            $plan->update(Arr::only($data, ['nutrition_guidance']));
        } else {
            $changes = Arr::only($data, ['target_weight_kg', 'target_waist_cm', 'doctor_notes', 'review_on', 'dietitian_id', 'nutrition_guidance']);
            if (array_key_exists('next_steps', $data)) {
                $changes['next_steps'] = array_values(array_filter($data['next_steps'] ?? []));
            }
            if (isset($data['status'])) {
                $changes['status'] = PlanStatus::from($data['status']);
            }
            $plan->update($changes);
        }

        Audit::log('plan.updated', $plan, ['by' => $by->type->value]);

        return $plan;
    }
}
