<?php

namespace App\Services;

use App\Enums\MerchantApproval;
use App\Enums\ShopStatus;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Commerce\AvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopService
{
    public function save(User $user, array $data, ?Shop $shop = null): Shop
    {
        return DB::transaction(function () use ($user, $data, $shop) {
            if ($shop) {
                Gate::forUser($user)->authorize('update', $shop);
                $vehicles = Vehicle::where('shop_id', $shop->id)->orderBy('id')->lockForUpdate()->get();
                $shop = Shop::lockForUpdate()->findOrFail($shop->id);
                Gate::forUser($user)->authorize('update', $shop);
                $sensitive = false;
                foreach (['country_code', 'city_id', 'district_id', 'latitude', 'longitude', 'timezone', 'status', 'currency_code'] as $field) {
                    $current = $shop->$field;
                    if ($current instanceof \BackedEnum) {
                        $current = $current->value;
                    }
                    if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $current) {
                        $sensitive = true;
                    }
                }
                if ($sensitive) {
                    foreach ($vehicles as $vehicle) {
                        app(AvailabilityService::class)->assertEditable($vehicle);
                    }
                }
                $shop = Shop::lockForUpdate()->findOrFail($shop->id);
                Gate::forUser($user)->authorize('update', $shop);
                if ($shop->status === ShopStatus::SUSPENDED) {
                    throw ValidationException::withMessages(['status' => ['Boutique suspendue : intervention administrative requise.']]);
                }
            } else {
                $merchant = MerchantProfile::findOrFail($data['merchant_id']);
                Gate::forUser($user)->authorize('create', [Shop::class, $merchant]);
                $shop = new Shop;
                $shop->merchant_id = $merchant->id;
                $shop->slug = substr(Str::slug($data['name']), 0, 200).'-'.strtolower(Str::ulid());
                unset($data['merchant_id']);
            }
            if ($shop->exists && ($data['country_code'] ?? $shop->country_code) !== $shop->country_code && (Vehicle::withTrashed()->where('shop_id', $shop->id)->exists() || Order::where('shop_id', $shop->id)->exists() || Reservation::where('shop_id', $shop->id)->exists())) {
                throw ValidationException::withMessages(['country_code' => ['Le pays ne peut plus changer après création des véhicules.']]);
            }
            $data['currency_code'] = app(CurrencyResolver::class)->forCountry($data['country_code'] ?? $shop->country_code, $data['currency_code'] ?? null);
            $shop->fill($data);
            if ($shop->status === ShopStatus::PUBLISHED && $shop->merchant->approval_status !== MerchantApproval::APPROVED) {
                throw ValidationException::withMessages(['status' => ['Le professionnel doit être approuvé avant publication.']]);
            }
            $shop->save();

            return $shop->refresh();
        });
    }
}
