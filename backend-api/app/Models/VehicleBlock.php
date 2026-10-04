<?php

namespace App\Models;

use App\Enums\BlockKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VehicleBlock extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['kind' => BlockKind::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];

    public function scopeBlocking(Builder $query): void
    {
        $query->whereNull('released_at')->where(fn ($q) => $q->where('kind', '!=', BlockKind::HOLD->value)->orWhere('expires_at', '>', now()));
    }
}
