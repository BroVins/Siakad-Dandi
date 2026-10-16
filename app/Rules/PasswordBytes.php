<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Bcrypt has a byte limit, not a Unicode-character limit. */
class PasswordBytes implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
            $fail('Kata sandi maksimal 72 byte. Kurangi panjang kata sandi, terutama karakter multibita.');
        }
    }
}
