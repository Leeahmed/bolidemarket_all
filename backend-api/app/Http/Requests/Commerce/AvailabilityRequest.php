<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Catalog\PaginationRequest;

class AvailabilityRequest extends PaginationRequest
{
    public function rules(): array
    {
        return parent::rules() + ['from' => ['sometimes', 'required', 'date_format:Y-m-d'], 'to' => ['required_with:from', 'date_format:Y-m-d', 'after:from']];
    }
}
