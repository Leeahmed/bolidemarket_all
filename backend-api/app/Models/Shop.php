<?php

namespace App\Models;

use App\Enums\MerchantApproval;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use SoftDeletes;

    public array $publicVehicleIdsForBroadcast = [];

    protected $guarded = ['id', 'merchant_id', 'slug', 'is_demo', 'logo_path', 'cover_path'];

    protected $casts = ['status' => ShopStatus::class, 'is_demo' => 'boolean', 'opening_hours' => 'array'];

    public const PUBLIC_RELATIONS = ['merchant.owner', 'country', 'city', 'district', 'currency'];

    public function merchant()
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function publicVehicles()
    {
        return $this->hasMany(Vehicle::class)->publiclyVisible();
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', ShopStatus::PUBLISHED)
            ->whereHas('merchant', fn ($q) => $q->where('approval_status', MerchantApproval::APPROVED)
                ->whereHas('owner', fn ($owner) => $owner->whereNull('disabled_at')->where('role', UserRole::MERCHANT)))
            ->whereHas('country', fn ($q) => $q->where('active', true))
            ->whereHas('city', fn ($q) => $q->where('active', true))
            ->where(fn ($q) => $q->whereNull('district_id')->orWhereHas('district', fn ($d) => $d->where('active', true)));
    }

    public function scopeManagedBy(Builder $query, User $user): void
    {
        $query->whereHas('merchant.members', fn ($q) => $q->where('users.id', $user->id));
    }
}
