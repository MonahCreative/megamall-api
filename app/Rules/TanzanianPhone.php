<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TanzanianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Phone::normalize($value) === null) {
            $fail('Enter a valid Tanzanian mobile number, for example 0712 345 678.');
        }
    }
}
