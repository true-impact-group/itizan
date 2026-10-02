<?php

namespace App\Http\Requests\Practitioner;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Doctor);
    }

    public function rules(): array
    {
        return [
            'slot_minutes' => ['required', Rule::in([10, 15, 20, 30, 45, 60])],
            'days' => ['nullable', 'array'],
            'days.*' => ['array', 'max:3'],
            'days.*.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.*.end' => ['nullable', 'date_format:H:i', 'required_with:days.*.*.start', 'after:days.*.*.start'],
        ];
    }
}
