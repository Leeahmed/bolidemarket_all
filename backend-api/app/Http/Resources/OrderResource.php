<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expired = $this->status === OrderStatus::PENDING && $this->expires_at?->isPast();

        return [
            'customer' => $this->when($request->is('api/v1/merchant/*') && $this->relationLoaded('customer'), fn () => $this->customer ? ['id' => (string) $this->customer->id, 'name' => $this->customer->name, 'email' => $this->customer->email, 'phone' => $this->customer->phone] : null),
            'id' => (string) $this->id, 'reference' => $this->reference, 'kind' => $this->kind, 'status' => $expired ? 'cancelled' : $this->status->value,
            'vehicle' => $this->vehicle_snapshot, 'shop' => $this->seller_snapshot, 'reservation_id' => $this->reservation_id ? (string) $this->reservation_id : null,
            'subtotal_minor' => $this->subtotal_minor, 'fees_minor' => $this->fees_minor, 'total_minor' => $this->total_minor, 'currency' => $this->currency_code, 'minor_unit' => $this->minor_unit,
            'payment_method_demo' => $this->payment_method_demo->value, 'payment' => new PaymentResource($this->whenLoaded('payment')),
            'expires_at' => $this->expires_at?->toISOString(), 'confirmed_at' => $this->confirmed_at?->toISOString(), 'fulfilled_at' => $this->fulfilled_at?->toISOString(), 'cancellation_reason' => $expired ? 'expired' : $this->cancellation_reason,
            'created_at' => $this->created_at->toISOString(), 'conditions_version' => $this->conditions_version, 'is_demo' => $this->is_demo, 'demo_notice' => 'Commande et paiement de démonstration. Aucun encaissement ni remboursement réel.'];
    }
}
