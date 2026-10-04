<?php

namespace App\Http\Requests\Catalog;

use App\Enums\FuelType;
use App\Enums\InventoryStatus;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use App\Models\City;
use App\Models\District;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VehicleSearchRequest extends PaginationRequest
{
    protected function prepareForValidation(): void
    {
        // Countries already use ISO codes as primary keys, never synthetic numeric IDs.
        if ($this->has('country_id') && ! $this->has('country_code')) {
            $this->merge(['country_code' => $this->input('country_id')]);
        }
    }

    public function rules(): array
    {
        $nearby = $this->routeIs('catalog.nearby');

        return parent::rules() + [
            'q' => ['sometimes', 'required', 'string', 'max:120'],
            'listing_type' => ['sometimes', 'required', Rule::in(['sale', 'rental'])],
            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'category' => ['sometimes', 'required', 'string', Rule::exists('categories', 'slug')],
            'condition' => ['sometimes', 'required', Rule::enum(VehicleCondition::class)],
            'fuel_type' => ['sometimes', 'required', Rule::enum(FuelType::class)],
            'transmission' => ['sometimes', 'required', Rule::enum(Transmission::class)],
            'status' => ['sometimes', 'required', Rule::enum(InventoryStatus::class)],
            'is_featured' => ['sometimes', 'required', 'boolean'],
            'is_certified' => ['sometimes', 'required', 'boolean'],
            'year_min' => ['sometimes', 'required', 'integer', 'between:1886,2100'],
            'year_max' => ['sometimes', 'required', 'integer', 'between:1886,2100'],
            'min_price' => ['sometimes', 'required', 'integer', 'between:0,99999999999999'],
            'max_price' => ['sometimes', 'required', 'integer', 'between:0,99999999999999'],
            'currency' => ['sometimes', 'required', 'string', Rule::exists('currencies', 'code')->where('active', true)],
            'country_id' => ['sometimes', 'required', 'string', 'size:2', Rule::exists('countries', 'code')->where('active', true)],
            'country_code' => ['sometimes', 'required', 'string', 'size:2', Rule::exists('countries', 'code')->where('active', true)],
            'city_id' => ['sometimes', 'required', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'district_id' => ['sometimes', 'required', 'integer', Rule::exists('districts', 'id')->where('active', true)],
            'location_mode' => ['sometimes', 'required', Rule::in(['filter', 'rank'])],
            'latitude' => [$nearby ? 'required' : 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => [$nearby ? 'required' : 'required_with:latitude', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:'.config('search.max_radius_km')],
            'sort' => ['sometimes', 'required', Rule::in($nearby ? ['distance'] : ['newest', 'price_asc', 'price_desc', 'year_desc', 'mileage_asc', 'distance', 'popular'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach (['year_min' => 'year_max', 'min_price' => 'max_price'] as $min => $max) {
                if ($this->has([$min, $max]) && $this->input($min) > $this->input($max)) {
                    $validator->errors()->add($max, 'La borne maximale doit être supérieure ou égale à la borne minimale.');
                }
            }
            if ($this->hasAny(['min_price', 'max_price']) || in_array($this->input('sort'), ['price_asc', 'price_desc'], true)) {
                foreach (['listing_type', 'currency'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, 'Intention et devise obligatoires pour comparer les prix.');
                    }
                }
            }
            if (($this->has('radius') || $this->input('sort') === 'distance') && ! $this->has(['latitude', 'longitude'])) {
                $validator->errors()->add('latitude', 'Une paire latitude/longitude est obligatoire pour la proximité.');
            }
            if ($this->input('sort') === 'popular') {
                $validator->errors()->add('sort', 'Le tri popular est réservé : aucune métrique de popularité fiable disponible.');
            }
            if ($this->has('country_id') && $this->input('country_id') !== $this->input('country_code')) {
                $validator->errors()->add('country_id', 'Utiliser le même code pays pour les deux alias.');
            }
            $district = $this->filled('district_id') ? District::find($this->input('district_id')) : null;
            $city = $this->filled('city_id') ? City::find($this->input('city_id')) : ($district ? City::find($district->city_id) : null);
            if ($district && $city && (int) $district->city_id !== (int) $city->id) {
                $validator->errors()->add('district_id', 'La commune doit appartenir à la ville.');
            }
            if ($city && (! $city->active || ($this->filled('country_code') && $city->country_code !== $this->input('country_code')))) {
                $validator->errors()->add('city_id', 'La ville doit être active et appartenir au pays.');
            }
        }];
    }
}
