<?php

namespace App\Http\Requests\Account;

use App\Enums\DoseStatus;
use App\Models\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LogDoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('logDose', $this->route('prescription'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DoseStatus::class)],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', Rule::in(['yes', 'no'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Prescription $prescription */
            $prescription = $this->route('prescription');

            if ($this->input('status') !== DoseStatus::Taken->value) {
                return;
            }

            foreach ($prescription->preDoseQuestions() as $index => $question) {
                if (! in_array($this->input("answers.$index"), ['yes', 'no'], true)) {
                    $validator->errors()->add('answers', __('أجب عن أسئلة ما قبل الجرعة.'));

                    return;
                }
            }
        }];
    }
}
