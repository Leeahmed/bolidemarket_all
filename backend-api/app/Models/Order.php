<?php

namespace App\Models;

use App\Enums\DemoPaymentMethod;
use App\Enums\OrderStatus;

class Order extends CommerceRecord
{
    protected $casts = ['buyer_snapshot' => 'array', 'handover' => 'array', 'status' => OrderStatus::class, 'payment_method_demo' => DemoPaymentMethod::class, 'expires_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'fulfilled_at' => 'immutable_datetime', 'subtotal_minor' => 'string', 'fees_minor' => 'string', 'total_minor' => 'string', 'vehicle_snapshot' => 'array', 'seller_snapshot' => 'array', 'is_demo' => 'boolean'];

    protected $with = ['receipt'];

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
