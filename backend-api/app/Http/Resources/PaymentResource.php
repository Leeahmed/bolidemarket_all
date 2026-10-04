<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['reference' => $this->reference, 'method' => $this->method->value, 'amount_minor' => $this->amount_minor, 'currency' => $this->currency_code, 'minor_unit' => $this->minor_unit, 'status' => $this->status->value, 'is_demo' => $this->is_demo, 'paid_at' => $this->paid_at?->toISOString(), 'notice' => 'Simulation uniquement. Aucun argent encaissé.'];
    }
}
