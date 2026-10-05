<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference, 'type' => $this->type->value, 'order_id' => (string) $this->order_id, 'shop_id' => (string) $this->shop_id,
            'currency' => $this->currency_code, 'minor_unit' => $this->minor_unit, 'subtotal_minor' => $this->subtotal_minor, 'fees_minor' => $this->fees_minor, 'total_minor' => $this->total_minor,
            'payment_method' => $this->payment_method, 'payment_status' => $this->payment_status, 'is_demo' => $this->is_demo, 'issued_at' => $this->issued_at->toISOString(),
            'buyer' => $this->buyer_snapshot, 'seller' => $this->seller_snapshot, 'vehicle' => $this->vehicle_snapshot, 'transaction' => $this->transaction_snapshot,
            'demo_notice' => 'Document généré dans le cadre d’une démonstration BolideMarket. Aucun paiement réel n’a été effectué.',
        ];
    }
}
