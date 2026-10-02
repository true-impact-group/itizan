<?php

namespace App\Http\Requests\Account;

use App\Actions\Assessment\SaveAssessment;
use App\Enums\Gender;
use App\Enums\PregnancyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** A draft may be partial; submitting for the doctor's review requires the essential information. */
class SaveAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function isSubmit(): bool
    {
        return $this->input('action') === 'submit';
    }

    public function rules(): array
    {
        $required = $this->isSubmit() ? 'required' : 'nullable';
        $isFemale = $this->user()->gender === Gender::Female;
        $text = ['nullable', 'string', 'max:2000'];

        return [
            'action' => ['required', Rule::in(['save', 'submit'])],
            'height_cm' => [$required, 'numeric', 'between:100,250'],
            'weight_kg' => [$required, 'numeric', 'between:30,400'],
            'waist_cm' => [$required, 'numeric', 'between:40,250'],
            'target_weight_kg' => ['nullable', 'numeric', 'between:30,400'],
            'pregnancy_status' => [Rule::requiredIf($this->isSubmit() && $isFemale), 'nullable', Rule::enum(PregnancyStatus::class)],
            'is_breastfeeding' => ['nullable', 'boolean'],
            'medical_history' => $text,
            'chronic_conditions' => [$required, 'string', 'max:2000'],
            'current_medications' => [$required, 'string', 'max:2000'],
            'allergies' => [$required, 'string', 'max:2000'],
            'past_surgeries' => $text,
            'previous_treatments' => $text,
            'current_symptoms' => $text,
            'available_labs' => $text,
            'goals' => [$required, 'string', 'max:2000'],
            'safety_answers' => ['nullable', 'array'],
            'safety_answers.*' => ['nullable', Rule::in(['yes', 'no'])],
            'medical_consent' => [$this->isSubmit() ? 'accepted' : 'nullable'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->isSubmit()) {
                return;
            }

            foreach (array_keys(config('itizan.safety_questions')) as $key) {
                if (! in_array($this->input("safety_answers.$key"), ['yes', 'no'], true)) {
                    $validator->errors()->add('safety_answers', __('أجب عن جميع أسئلة السلامة.'));

                    return;
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function assessmentData(): array
    {
        $data = $this->safe()->only([...SaveAssessment::FIELDS, 'safety_answers']);
        $data['is_breastfeeding'] = $this->has('is_breastfeeding') ? $this->boolean('is_breastfeeding') : null;

        return $data;
    }
}
