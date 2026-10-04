<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalQuote extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'daily_price_minor' => 'string', 'total_minor' => 'string'];
}
