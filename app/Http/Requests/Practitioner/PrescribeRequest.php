<?php

namespace App\Http\Requests\Practitioner;

use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Doctor);
    }

    public function rules(): array
    {
        return [
            'medication_name' => ['required', 'string', 'max:150'],
            'dose' => ['required', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'interval_days' => ['required', 'integer', 'between:1,30'],
            'dose_time' => ['required', 'date_format:H:i'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'product_id' => ['nullable', Rule::exists(Product::class, 'id')->where('type', ProductType::Medication->value)],
            'pre_dose_questions' => ['nullable', 'array', 'max:5'],
            'pre_dose_questions.*.text' => ['nullable', 'string', 'max:200'],
            'pre_dose_questions.*.alert_on' => ['nullable', Rule::in(['yes', 'no'])],
        ];
    }

    /** Questions without text are dropped; the alert answer defaults to "yes". */
    public function prescriptionData(): array
    {
        $data = $this->validated();
        $data['pre_dose_questions'] = collect($data['pre_dose_questions'] ?? [])
            ->filter(fn ($question) => filled($question['text'] ?? null))
            ->map(fn ($question) => ['text' => $question['text'], 'alert_on' => $question['alert_on'] ?? 'yes'])
            ->values()
            ->all();

        return $data;
    }
}
