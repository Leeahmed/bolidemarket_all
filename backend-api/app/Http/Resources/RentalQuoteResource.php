<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'vehicle_id' => (string) $this->vehicle_id, 'starts_at' => $this->starts_at->toISOString(), 'ends_at' => $this->ends_at->toISOString(), 'shop_timezone' => $this->shop_timezone, 'billable_days' => $this->billable_days, 'daily_price_minor' => $this->daily_price_minor, 'total_minor' => $this->total_minor, 'currency' => $this->currency_code, 'minor_unit' => $this->minor_unit, 'expires_at' => $this->expires_at->toISOString(), 'conditions_version' => config('commerce.conditions_version'), 'is_demo' => true, 'notice' => 'Devis de démonstration ; aucune retenue avant réservation.'];
    }
}
