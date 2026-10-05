<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\InventoryStatus;
use App\Enums\PublicationStatus;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public bool $wasPublicForBroadcast = false;

    protected $guarded = ['id', 'reference', 'slug', 'publication_status', 'inventory_status', 'published_at', 'version', 'is_demo', 'is_featured', 'is_certified'];

    protected $casts = [
        'fuel' => FuelType::class, 'transmission' => Transmission::class, 'condition' => VehicleCondition::class,
        'publication_status' => PublicationStatus::class, 'inventory_status' => InventoryStatus::class,
        'negotiation_enabled' => 'boolean', 'is_for_sale' => 'boolean', 'is_for_rent' => 'boolean', 'is_demo' => 'boolean',
        'is_featured' => 'boolean', 'is_certified' => 'boolean', 'published_at' => 'datetime',
        'sale_price_minor' => 'string', 'rent_daily_minor' => 'string',
    ];

    public const PUBLIC_RELATIONS = [
        'vehicleModel.brand', 'category', 'currency', 'images', 'features', 'country', 'city', 'district',
        'shop.merchant.owner', 'shop.country', 'shop.city', 'shop.district', 'shop.currency',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
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

    public function images()
    {
        return $this->hasMany(VehicleImage::class)->orderBy('position');
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class);
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('publication_status', PublicationStatus::PUBLISHED)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereHas('shop', fn ($q) => $q->publiclyVisible())
            ->whereHas('currency', fn ($q) => $q->where('active', true))
            ->whereHas('country', fn ($q) => $q->where('active', true))
            ->whereHas('city', fn ($q) => $q->where('active', true))
            ->where(fn ($q) => $q->whereNull('vehicles.district_id')->orWhereHas('district', fn ($d) => $d->where('active', true)));
    }

    public function scopeManagedBy(Builder $query, User $user): void
    {
        $query->whereHas('shop', fn ($q) => $q->managedBy($user));
    }
}
