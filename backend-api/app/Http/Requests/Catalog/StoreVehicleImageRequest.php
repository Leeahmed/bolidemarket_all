<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('vehicle'));
    }

    public function rules(): array
    {
        return ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'alt_text' => ['nullable', 'string', 'max:255']];
    }
}
