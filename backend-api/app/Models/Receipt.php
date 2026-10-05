<?php

namespace App\Models;

use App\Enums\ReceiptType;
use LogicException;

class Receipt extends CommerceRecord
{
    protected $casts = ['type' => ReceiptType::class, 'is_demo' => 'boolean', 'issued_at' => 'immutable_datetime', 'subtotal_minor' => 'string', 'fees_minor' => 'string', 'total_minor' => 'string', 'buyer_snapshot' => 'array', 'seller_snapshot' => 'array', 'vehicle_snapshot' => 'array', 'transaction_snapshot' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un reçu émis est immuable.'));
        static::deleting(fn () => throw new LogicException('Un reçu émis ne peut pas être supprimé.'));
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
