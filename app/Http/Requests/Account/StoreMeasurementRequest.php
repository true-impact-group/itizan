<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'measured_on' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'weight_kg' => ['required', 'numeric', 'between:20,500'],
            'waist_cm' => ['nullable', 'numeric', 'between:30,250'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
