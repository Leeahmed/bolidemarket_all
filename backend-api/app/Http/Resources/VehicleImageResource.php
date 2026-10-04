<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VehicleImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'url' => Storage::disk('public')->url($this->storage_key),
            'alt_text' => $this->alt_text, 'position' => $this->position, 'is_primary' => $this->position === 0,
            'is_placeholder' => $this->is_placeholder];
    }
}
