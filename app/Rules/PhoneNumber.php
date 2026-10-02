<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Accepts Saudi mobile numbers and plausible international numbers; requests normalise before validating. */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Phone::normalize((string) $value) === null) {
            $fail(__('validation.phone'));
        }
    }
}
