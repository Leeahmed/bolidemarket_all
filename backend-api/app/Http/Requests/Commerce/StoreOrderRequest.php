<?php

namespace App\Http\Requests\Commerce;

use App\Enums\DemoPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canUseCommerce();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'price_offer_id' => ['nullable', 'integer', 'min:1'],
            'handover' => ['required', 'array:mode,scheduled_local,contact_name,contact_phone,address,city,latitude,longitude,notes'],
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::enum(DemoPaymentMethod::class)],
            'expected_price_minor' => ['sometimes', 'integer', 'min:0', 'max:99999999999999'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'conditions_version' => ['sometimes', Rule::in([config('commerce.conditions_version')])],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
