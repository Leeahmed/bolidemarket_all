<?php

namespace App\Rules;

use App\Services\PhoneNumbers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InternationalPhone implements ValidationRule
{
    public function __construct(private ?string $country = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(PhoneNumbers::class)->normalize($value, $this->country)) {
            $fail('Saisissez un numéro valide pour le pays sélectionné.');
        }
    }
}
