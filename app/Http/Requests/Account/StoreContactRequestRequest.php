<?php

namespace App\Http\Requests\Account;

use App\Enums\ContactTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'target' => ['required', Rule::enum(ContactTarget::class)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
