<?php

namespace App\Models;

use App\Enums\DemoPaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['status' => PaymentStatus::class, 'method' => DemoPaymentMethod::class, 'is_demo' => 'boolean', 'paid_at' => 'immutable_datetime', 'amount_minor' => 'string'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
