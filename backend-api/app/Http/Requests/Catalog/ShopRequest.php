<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ShopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShopRequest extends FormRequest
{
    use LocationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($shop = $this->route('shop')) {
            $this->merge(array_replace(Arr::only($shop->attributesToArray(), [
                'name', 'description', 'email', 'phone', 'address', 'country_code', 'city_id',
                'district_id', 'timezone', 'latitude', 'longitude', 'status',
            ]), $this->all()));
        }
    }

    public function rules(): array
    {
        return $this->locationRules() + [
            'merchant_id' => $this->route('shop') ? ['prohibited'] : ['required', 'integer', 'exists:merchant_profiles,id'],
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'address' => ['required', 'string', 'max:255'],
            'currency_code' => ['sometimes', 'string', Rule::exists('currencies', 'code')->where('active', true)],
            'timezone' => ['required', 'timezone'],
            'status' => ['sometimes', Rule::in([ShopStatus::DRAFT->value, ShopStatus::PUBLISHED->value])],
            'opening_hours' => ['sometimes', 'nullable', 'array', 'size:7'],
            'opening_hours.*' => ['array:day,closed,opens,closes'],
            'opening_hours.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
            'opening_hours.*.closed' => ['required', 'boolean'],
            'opening_hours.*.opens' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closes' => ['nullable', 'date_format:H:i'],
            'slug' => ['prohibited'], 'logo_path' => ['prohibited'], 'cover_path' => ['prohibited'], 'is_demo' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $this->validateLocation($v);
            foreach ((array) $this->input('opening_hours', []) as $i => $day) {
                if (is_array($day) && ! ($day['closed'] ?? true) && (empty($day['opens']) || empty($day['closes']) || $day['closes'] <= $day['opens'])) {
                    $v->errors()->add("opening_hours.$i", 'Indiquez une ouverture et une fermeture plus tard le même jour.');
                }
            }
        }];
    }
}
