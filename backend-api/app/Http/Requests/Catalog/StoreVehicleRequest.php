<?php

namespace App\Http\Requests\Catalog;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
{
    use LocationRules;

    public const EDITABLE = [
        'negotiation_enabled', 'vehicle_model_id', 'category_id', 'title', 'trim', 'year', 'condition', 'is_for_sale', 'is_for_rent',
        'sale_price_minor', 'rent_daily_minor', 'currency_code', 'mileage_km', 'fuel', 'transmission',
        'engine', 'horsepower', 'doors', 'seats', 'color', 'vin', 'license_plate', 'description',
        'country_code', 'city_id', 'district_id', 'latitude', 'longitude',
    ];

    public function authorize(): bool
    {
        return ! $this->route('vehicle') || $this->user()->can('update', $this->route('vehicle'));
    }

    protected function prepareForValidation(): void
    {
        if ($vehicle = $this->route('vehicle')) {
            $defaults = Arr::only($vehicle->attributesToArray(), self::EDITABLE);
        } else {
            $shop = is_scalar($this->input('shop_id')) ? Shop::find($this->input('shop_id')) : null;
            $defaults = $shop ? Arr::only($shop->attributesToArray(), ['country_code', 'city_id', 'district_id', 'currency_code', 'latitude', 'longitude']) : [];
            $defaults += ['is_for_sale' => false, 'is_for_rent' => false];
        }
        $this->merge(array_replace($defaults, $this->all()));
    }

    public function rules(): array
    {
        return $this->locationRules() + [
            'shop_id' => $this->route('vehicle') ? ['prohibited'] : ['required', 'integer', Rule::exists('shops', 'id')->whereNull('deleted_at')],
            'vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'negotiation_enabled' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:180'], 'trim' => ['nullable', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
            'condition' => ['required', Rule::enum(VehicleCondition::class)],
            'is_for_sale' => ['required', 'boolean'], 'is_for_rent' => ['required', 'boolean'],
            'sale_price_minor' => ['nullable', Rule::requiredIf(fn () => $this->boolean('is_for_sale')), 'integer', 'min:1', 'max:99999999999999'],
            'rent_daily_minor' => ['nullable', Rule::requiredIf(fn () => $this->boolean('is_for_rent')), 'integer', 'min:1', 'max:99999999999999'],
            'currency_code' => ['required', 'string', Rule::exists('currencies', 'code')->where('active', true)],
            'mileage_km' => ['nullable', 'integer', 'between:0,10000000'],
            'fuel' => ['required', Rule::enum(FuelType::class)], 'transmission' => ['required', Rule::enum(Transmission::class)],
            'engine' => ['nullable', 'string', 'max:100'], 'horsepower' => ['nullable', 'integer', 'between:1,3000'],
            'doors' => ['nullable', 'integer', 'between:1,10'], 'seats' => ['nullable', 'integer', 'between:1,60'],
            'color' => ['nullable', 'string', 'max:80'], 'vin' => ['nullable', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'license_plate' => ['nullable', 'string', 'max:32'], 'description' => ['required', 'string', 'max:10000'],
            'feature_ids' => ['sometimes', 'array', 'max:30'], 'feature_ids.*' => ['integer', 'distinct', 'exists:features,id'],
            'reference' => ['prohibited'], 'slug' => ['prohibited'], 'publication_status' => ['prohibited'],
            'inventory_status' => ['prohibited'], 'published_at' => ['prohibited'], 'version' => ['prohibited'],
            'is_featured' => ['prohibited'], 'is_certified' => ['prohibited'], 'is_demo' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $this->validateLocation($v);
            if (! $this->boolean('is_for_sale') && ! $this->boolean('is_for_rent')) {
                $v->errors()->add('is_for_sale', 'Choisir la vente, la location ou les deux.');
            }
        }];
    }
}
