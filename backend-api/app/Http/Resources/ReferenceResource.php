<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = Arr::only($this->resource->attributesToArray(), [
            'id', 'code', 'name', 'label', 'slug', 'country_code', 'city_id', 'brand_id',
            'currency_code', 'currency_symbol', 'minor_unit', 'phone_code', 'active', 'latitude', 'longitude',
        ]);
        foreach (['id', 'city_id', 'brand_id'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (string) $data[$key];
            }
        }

        return $data;
    }
}
