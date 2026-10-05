<?php

namespace App\Models;

class PriceOffer extends CommerceRecord
{
    protected $casts = ['amount_minor' => 'string', 'asking_price_minor' => 'string', 'vehicle_snapshot' => 'array', 'seller_snapshot' => 'array', 'expires_at' => 'immutable_datetime', 'responded_at' => 'immutable_datetime', 'is_demo' => 'boolean'];
}
