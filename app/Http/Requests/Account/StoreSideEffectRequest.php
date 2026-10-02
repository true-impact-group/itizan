<?php

namespace App\Http\Requests\Account;

use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSideEffectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'symptom' => ['required', 'string', 'min:3', 'max:2000'],
            'severity' => ['required', Rule::enum(Severity::class)],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'prescription_id' => ['nullable', 'integer'],
            'files' => ['nullable', 'array', 'max:3'],
            'files.*' => ['file', 'mimes:'.implode(',', config('itizan.uploads.mimes')), 'max:'.config('itizan.uploads.max_kb')],
        ];
    }
}
