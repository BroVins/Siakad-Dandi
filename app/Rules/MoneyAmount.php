<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MoneyAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                throw new \InvalidArgumentException;
            }
            if (Money::cents($value) < 1) {
                throw new \InvalidArgumentException;
            }
        } catch (\InvalidArgumentException $e) {
            $fail('Nominal wajib antara 0.01 dan 99999999999.99, maksimal dua angka desimal. Gunakan titik sebagai pemisah, tanpa notasi eksponen.');
        }
    }
}
