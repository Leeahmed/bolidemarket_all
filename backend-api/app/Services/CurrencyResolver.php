<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;

class CurrencyResolver
{
    public function forCountry(string $code, ?string $requested = null): string
    {
        $country = Country::with('currency')->where('active', true)->find($code);
        if (! $country || ! $country->currency?->active) {
            throw ValidationException::withMessages(['country_code' => ['Marché ou devise indisponible.']]);
        }
        if ($requested !== null && $requested !== $country->currency_code) {
            throw ValidationException::withMessages(['currency_code' => ['La devise est imposée par le pays de la boutique.']]);
        }

        return $country->currency_code;
    }

    public function forShop(Shop $shop, array $data): string
    {
        if (isset($data['country_code']) && $data['country_code'] !== $shop->country_code) {
            throw ValidationException::withMessages(['country_code' => ['Le pays de l’annonce doit correspondre à celui de sa boutique.']]);
        }

        return $this->forCountry($shop->country_code, $data['currency_code'] ?? null);
    }
}
