<?php

namespace App\Http\Requests\Practitioner;

use App\Enums\AssessmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('assessment'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([AssessmentStatus::Approved->value, AssessmentStatus::NeedsInfo->value, AssessmentStatus::NotSuitable->value])],
            'notes' => [Rule::requiredIf($this->input('decision') !== AssessmentStatus::Approved->value), 'nullable', 'string', 'max:3000'],
        ];
    }
}
