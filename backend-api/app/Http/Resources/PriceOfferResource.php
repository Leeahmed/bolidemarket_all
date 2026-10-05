<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'vehicle_id' => (string) $this->vehicle_id, 'shop_id' => (string) $this->shop_id,
            'vehicle' => $this->vehicle_snapshot, 'shop' => $this->seller_snapshot,
            'status' => in_array($this->status, ['pending', 'accepted']) && $this->expires_at->isPast() ? 'expired' : $this->status,
            'amount_minor' => $this->amount_minor, 'asking_price_minor' => $this->asking_price_minor, 'currency' => $this->currency_code, 'minor_unit' => $this->minor_unit,
            'expires_at' => $this->expires_at->toISOString(), 'created_at' => $this->created_at->toISOString(), 'is_demo' => $this->is_demo,
            'customer' => $this->when($request->is('api/v1/merchant/*') && $this->relationLoaded('customer'), fn () => ['name' => $this->customer->name])];
    }
}
