<?php

namespace App\Http\Resources;

use App\Enums\InventoryStatus;
use App\Enums\ListingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    private function money(?string $amount, ?string $unit = null): ?array
    {
        return $amount === null ? null : ['amount_minor' => $amount, 'currency' => $this->currency_code,
            'minor_unit' => $this->currency->minor_unit, 'unit' => $unit];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id, 'reference' => $this->reference, 'slug' => $this->slug, 'title' => $this->title,
            'brand' => new ReferenceResource($this->vehicleModel->brand),
            'model' => new ReferenceResource($this->vehicleModel), 'trim' => $this->trim,
            'year' => $this->year, 'condition' => $this->condition->value,
            'category' => new ReferenceResource($this->category),
            'offer_types' => array_values(array_filter([$this->is_for_sale ? ListingType::SALE->value : null, $this->is_for_rent ? ListingType::RENTAL->value : null])),
            'negotiation_enabled' => $this->is_for_sale && $this->negotiation_enabled,
            'sale_price' => $this->is_for_sale ? $this->money($this->sale_price_minor) : null,
            'rental_daily_price' => $this->is_for_rent ? $this->money($this->rent_daily_minor, 'day') : null,
            'mileage_km' => $this->mileage_km, 'fuel' => $this->fuel->value, 'transmission' => $this->transmission->value,
            'engine' => $this->engine, 'horsepower' => $this->horsepower, 'doors' => $this->doors, 'seats' => $this->seats,
            'color' => $this->color, 'description' => $this->description,
            'publication_status' => $this->publication_status->value, 'inventory_status' => $this->inventory_status->value,
            'is_available_now' => $this->inventory_status === InventoryStatus::AVAILABLE,
            'future_availability' => null,
            'distance_km' => $this->distance_km === null ? null : round((float) $this->distance_km, 3),
            'is_featured' => $this->is_featured, 'is_certified' => $this->is_certified,
            'created_at' => $this->created_at?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
            'images' => VehicleImageResource::collection($this->images),
            'primary_image' => $this->images->firstWhere('position', 0) ? new VehicleImageResource($this->images->firstWhere('position', 0)) : null,
            'features' => ReferenceResource::collection($this->features),
            'shop' => new ShopResource($this->whenLoaded('shop')),
            'location' => ['country' => new ReferenceResource($this->country), 'city' => new ReferenceResource($this->city),
                'district' => $this->district ? new ReferenceResource($this->district) : null,
                'latitude' => $this->latitude, 'longitude' => $this->longitude],
            'vin' => $this->when($request->is('api/v1/merchant/*'), $this->vin),
            'license_plate' => $this->when($request->is('api/v1/merchant/*'), $this->license_plate),
            'is_demo' => $this->is_demo, 'demo_notice' => $this->is_demo ? 'Véhicule, prix et données de démonstration.' : null,
        ];
    }
}
