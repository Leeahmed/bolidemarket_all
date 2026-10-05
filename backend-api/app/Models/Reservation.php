<?php

namespace App\Models;

use App\Enums\DemoPaymentMethod;
use App\Enums\ReservationStatus;

class Reservation extends CommerceRecord
{
    protected $casts = ['buyer_snapshot' => 'array', 'status' => ReservationStatus::class, 'payment_method_demo' => DemoPaymentMethod::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'daily_price_minor' => 'string', 'subtotal_minor' => 'string', 'fees_minor' => 'string', 'total_minor' => 'string', 'vehicle_snapshot' => 'array', 'seller_snapshot' => 'array', 'is_demo' => 'boolean'];

    public function order()
    {
        return $this->hasOne(Order::class);
    }
}
