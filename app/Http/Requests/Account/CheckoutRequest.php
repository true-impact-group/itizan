<?php

namespace App\Http\Requests\Account;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null]);
    }

    public function rules(): array
    {
        return [
            // Receipts and dose reminders need an email: clients who registered with a phone only must add one to subscribe.
            'email' => [Rule::requiredIf(blank($this->user()->email)), 'nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // Automatic renewal is its own opt-in checkbox, never bundled with the terms.
            'auto_renew' => ['nullable', 'boolean'],
            // Price, VAT, renewal, cancellation and refund policies were shown before payment.
            'accept_terms' => ['accepted'],
        ];
    }
}
