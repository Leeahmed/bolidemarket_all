<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'cover_url' => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null,
            'opening_hours' => $this->opening_hours,
            'description' => $this->description, 'email' => $this->email, 'phone' => $this->phone,
            'address' => $this->address, 'timezone' => $this->timezone, 'status' => $this->status->value,
            'currency' => new ReferenceResource($this->currency),
            'location' => ['country' => new ReferenceResource($this->country), 'city' => new ReferenceResource($this->city),
                'district' => $this->district ? new ReferenceResource($this->district) : null,
                'latitude' => $this->latitude, 'longitude' => $this->longitude],
            'merchant' => ['id' => (string) $this->merchant_id, 'display_name' => $this->merchant->display_name,
                'is_verified' => $this->merchant->verified_at !== null],
            'published_vehicles_count' => $this->whenCounted('publicVehicles'),
            'sale_vehicles_count' => $this->whenHas('sale_vehicles_count'),
            'rental_vehicles_count' => $this->whenHas('rental_vehicles_count'),
            'vehicles' => VehicleResource::collection($this->whenLoaded('recentVehicles')),
            'is_demo' => $this->is_demo,
            'demo_notice' => $this->is_demo ? 'Boutique et données de démonstration.' : null,
        ];
    }
}
