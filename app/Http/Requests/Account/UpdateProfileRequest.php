<?php

namespace App\Http\Requests\Account;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'city' => ['nullable', 'string', Rule::in(config('itizan.cities'))],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'marketing_consent' => ['nullable', 'boolean'],
        ];
    }
}
