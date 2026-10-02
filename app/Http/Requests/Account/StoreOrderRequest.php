<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            // One row per catalogue product with a quantity; 0 means "not ordered".
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'between:0,10'],
            'prescription_id' => ['nullable', 'integer'],
            'delivery_city' => ['required', Rule::in(config('itizan.cities'))],
            'delivery_address' => ['required', 'string', 'min:8', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
