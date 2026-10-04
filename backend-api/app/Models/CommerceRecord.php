<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class CommerceRecord extends Model
{
    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function scopeForCustomer(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    public function scopeForMerchant(Builder $query, User $user): void
    {
        $query->whereHas('shop', fn ($q) => $q->managedBy($user));
    }
}
