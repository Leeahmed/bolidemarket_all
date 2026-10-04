<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['country_code' => ['sometimes', 'string', 'size:2'], 'city_id' => ['sometimes', 'integer', 'min:1'],
            'brand_id' => ['sometimes', 'integer', 'min:1']];
    }
}
