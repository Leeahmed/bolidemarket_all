<?php

namespace App\Http\Requests\Catalog;

use App\Models\City;
use App\Models\District;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait LocationRules
{
    protected function locationRules(): array
    {
        return [
            'country_code' => ['required', 'string', Rule::exists('countries', 'code')->where('active', true)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where('active', true)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    protected function validateLocation(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $city = City::find($this->input('city_id'));
        if ($city?->country_code !== $this->input('country_code')) {
            $validator->errors()->add('city_id', 'La ville doit appartenir au pays choisi.');
        }
        if ($this->filled('district_id') && District::find($this->input('district_id'))?->city_id !== $city?->id) {
            $validator->errors()->add('district_id', 'La commune doit appartenir à la ville choisie.');
        }
        if ($this->filled('latitude') !== $this->filled('longitude')) {
            $validator->errors()->add('latitude', 'Fournir les deux coordonnées ou aucune.');
        }
    }
}
