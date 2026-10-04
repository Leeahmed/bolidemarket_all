<?php

namespace App\Http\Requests\Auth;

use App\Models\City;
use App\Models\District;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MerchantRegisterRequest extends RegisterRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'role' => ['prohibited'], 'approval_status' => ['prohibited'], 'is_demo' => ['prohibited'],
            'shop' => ['required', 'array:name,activity_type,address,country_code,city_id,district_id,timezone,logo,cover'],
            'shop.name' => ['required', 'string', 'max:150'],
            'shop.activity_type' => ['required', Rule::in(['sale', 'rental', 'both'])],
            'shop.address' => ['required', 'string', 'max:255'],
            'shop.country_code' => ['required', 'string', Rule::exists('countries', 'code')->where('active', true)],
            'shop.city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'shop.district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where('active', true)],
            'shop.timezone' => ['required', 'timezone'],
            'shop.logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=4096,max_height=4096'],
            'shop.cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=4096,max_height=4096'],
        ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $city = City::find($this->input('shop.city_id'));
            if ($city?->country_code !== $this->input('shop.country_code')) {
                $v->errors()->add('shop.city_id', 'La ville doit appartenir au pays de la boutique.');
            }
            if ($this->filled('shop.district_id') && (string) District::find($this->input('shop.district_id'))?->city_id !== (string) $city?->id) {
                $v->errors()->add('shop.district_id', 'La commune doit appartenir à la ville choisie.');
            }
        }];
    }
}
