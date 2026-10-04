<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BcryptPasswordInput implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt limits bytes, not Unicode characters, and does not accept NUL.
        if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
            $fail('Le mot de passe doit contenir au plus 72 octets et aucun caractère nul.');
        }
    }
}
