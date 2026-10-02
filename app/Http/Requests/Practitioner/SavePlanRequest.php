<?php

namespace App\Http\Requests\Practitioner;

use App\Enums\PlanStatus;
use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Models\Practitioner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Doctors edit the whole plan; a dietitian may only edit the nutrition guidance. */
class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Doctor, Role::Dietitian);
    }

    public function rules(): array
    {
        $guidance = ['nullable', 'string', 'max:4000'];

        if ($this->user()->hasRole(Role::Dietitian)) {
            return ['nutrition_guidance' => $guidance];
        }

        return [
            'target_weight_kg' => ['required', 'numeric', 'between:30,400'],
            'target_waist_cm' => ['nullable', 'numeric', 'between:40,250'],
            'doctor_notes' => ['nullable', 'string', 'max:3000'],
            'next_steps' => ['nullable', 'array', 'max:10'],
            'next_steps.*' => ['nullable', 'string', 'max:200'],
            'review_on' => ['nullable', 'date', 'after:today'],
            'dietitian_id' => ['nullable', Rule::exists(Practitioner::class, 'id')->where('type', PractitionerType::Dietitian->value)],
            'nutrition_guidance' => $guidance,
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
        ];
    }
}
