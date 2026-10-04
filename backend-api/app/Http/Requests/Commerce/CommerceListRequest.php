<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Catalog\PaginationRequest;
use Illuminate\Validation\Rule;

class CommerceListRequest extends PaginationRequest
{
    public function rules(): array
    {
        return parent::rules() + ['q' => ['sometimes', 'string', 'max:120'], 'kind' => ['sometimes', Rule::in(['sale', 'rental'])], 'rentals' => ['sometimes', 'boolean'], 'shop_id' => ['sometimes', 'integer', 'min:1'], 'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected', 'expired', 'fulfilled'])]];
    }
}
