<?php

namespace App\Http\Requests\Catalog;

use App\Enums\InventoryStatus;
use App\Enums\PublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('vehicle'));
    }

    public function rules(): array
    {
        return [
            'publication_status' => ['required_without:inventory_status', Rule::enum(PublicationStatus::class)],
            'inventory_status' => ['required_without:publication_status', Rule::in([InventoryStatus::AVAILABLE->value, InventoryStatus::OTHER->value])],
        ];
    }
}
