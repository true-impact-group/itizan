<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone'),
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'phone' => ['required', new PhoneNumber, Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'city' => ['nullable', 'string', Rule::in(config('itizan.cities'))],
            'terms' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function registrationData(): array
    {
        return [
            ...$this->safe()->except(['terms', 'password_confirmation', 'marketing_consent']),
            'locale' => app()->getLocale(),
            'marketing_consent' => $this->boolean('marketing_consent'),
        ];
    }
}
