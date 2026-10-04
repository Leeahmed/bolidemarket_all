<?php

namespace App\Http\Resources;

use App\Enums\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expired = $this->status === ReservationStatus::PENDING && $this->expires_at?->isPast();

        return [
            'customer' => $this->when($request->is('api/v1/merchant/*') && $this->relationLoaded('customer'), fn () => $this->customer ? ['id' => (string) $this->customer->id, 'name' => $this->customer->name, 'email' => $this->customer->email, 'phone' => $this->customer->phone] : null),
            'id' => (string) $this->id, 'reference' => $this->reference, 'vehicle' => $this->vehicle_snapshot, 'shop' => $this->seller_snapshot,
            'starts_at' => $this->starts_at->toISOString(), 'ends_at' => $this->ends_at->toISOString(), 'shop_timezone' => $this->shop_timezone, 'billable_days' => $this->billable_days,
            'daily_price_minor' => $this->daily_price_minor, 'subtotal_minor' => $this->subtotal_minor, 'fees_minor' => $this->fees_minor, 'total_minor' => $this->total_minor, 'currency' => $this->currency_code, 'minor_unit' => $this->minor_unit,
            'status' => $expired ? 'expired' : $this->status->value, 'expires_at' => $this->expires_at?->toISOString(), 'payment_method_demo' => $this->payment_method_demo->value,
            'order' => new OrderResource($this->whenLoaded('order')), 'created_at' => $this->created_at->toISOString(), 'conditions_version' => $this->conditions_version, 'is_demo' => $this->is_demo,
            'demo_notice' => 'Réservation de démonstration. La confirmation du professionnel est requise.'];
    }
}
