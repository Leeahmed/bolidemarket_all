<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Validation\Rule;

class MerchantVehicleListRequest extends PaginationRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'shop_id' => ['sometimes', 'integer', 'min:1'],
            'q' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['available', 'rented', 'sold', 'other'])],
            'listing_type' => ['sometimes', Rule::in(['sale', 'rental'])],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'brand_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
