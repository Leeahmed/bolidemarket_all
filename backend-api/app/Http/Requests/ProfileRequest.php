<?php

namespace App\Http\Requests;

use App\Models\City;
use App\Models\District;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', Rule::exists('countries', 'code')->where('active', true)],
            'phone' => ['required', 'string', 'max:40', new InternationalPhone(is_string($this->country_code) ? $this->country_code : null)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where('active', true)],
            'email' => ['prohibited'], 'role' => ['prohibited'], 'user_id' => ['prohibited'],
            'email_verified_at' => ['prohibited'], 'avatar_path' => ['prohibited'], 'disabled_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('city_id') && City::find($this->city_id)?->country_code !== $this->country_code) {
                $v->errors()->add('city_id', 'La ville doit appartenir au pays choisi.');
            }
            if ($this->filled('district_id') && (! $this->filled('city_id') || (string) District::find($this->district_id)?->city_id !== (string) $this->city_id)) {
                $v->errors()->add('district_id', 'La commune doit appartenir à la ville choisie.');
            }
        }];
    }
}
