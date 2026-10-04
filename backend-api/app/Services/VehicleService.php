<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Enums\PublicationStatus;
use App\Models\City;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\Commerce\AvailabilityService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function save(User $user, array $data, ?Vehicle $vehicle = null): Vehicle
    {
        return DB::transaction(function () use ($user, $data, $vehicle) {
            if ($vehicle) {
                $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
                Gate::forUser($user)->authorize('update', $vehicle);
                $this->assertEditable($vehicle);
            } else {
                $shop = Shop::lockForUpdate()->findOrFail($data['shop_id']);
                Gate::forUser($user)->authorize('update', $shop);
                $model = VehicleModel::with('brand')->findOrFail($data['vehicle_model_id']);
                $data['title'] ??= $model->brand->name.' '.$model->name;
                $vehicle = new Vehicle;
                $suffix = (string) Str::ulid();
                $vehicle->reference = 'BM-'.$data['country_code'].'-'.$suffix;
                $vehicle->slug = substr(Str::slug($data['title'].'-'.$data['year'].'-'.City::findOrFail($data['city_id'])->name), 0, 200).'-'.strtolower($suffix);
            }
            $marketShop = $shop ?? Shop::lockForUpdate()->findOrFail($vehicle->shop_id);
            $data['currency_code'] = app(CurrencyResolver::class)->forShop($marketShop, $data);
            if (! $vehicle->exists) {
                $vehicle->is_demo = $marketShop->is_demo;
            }
            if (! $data['is_for_sale']) {
                $data['sale_price_minor'] = null;
            }
            if (! $data['is_for_rent']) {
                $data['rent_daily_minor'] = null;
            }
            $vehicle->fill(Arr::except($data, 'feature_ids'));
            $vehicle->version = ($vehicle->version ?? 0) + 1;
            $vehicle->save();
            if (array_key_exists('feature_ids', $data)) {
                $vehicle->features()->sync($data['feature_ids']);
            }
            if ($vehicle->publication_status === PublicationStatus::PUBLISHED) {
                $this->assertPublishable($vehicle);
            }

            return $vehicle->refresh();
        });
    }

    public function changeStatus(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
            Gate::authorize('update', $vehicle);
            $this->assertEditable($vehicle);
            if (isset($data['publication_status'])) {
                $vehicle->publication_status = PublicationStatus::from($data['publication_status']);
                if ($vehicle->publication_status === PublicationStatus::PUBLISHED) {
                    $this->assertPublishable($vehicle);
                    $vehicle->published_at ??= now();
                }
            }
            if (isset($data['inventory_status'])) {
                $vehicle->inventory_status = InventoryStatus::from($data['inventory_status']);
            }
            $vehicle->version++;
            $vehicle->save();

            return $vehicle;
        });
    }

    public function delete(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
            Gate::authorize('delete', $vehicle);
            $this->assertEditable($vehicle);
            $vehicle->delete();
        });
    }

    public function assertEditable(Vehicle $vehicle): void
    {
        app(AvailabilityService::class)->assertEditable($vehicle);
        if (in_array($vehicle->inventory_status, [InventoryStatus::RENTED, InventoryStatus::SOLD], true)) {
            throw ValidationException::withMessages(['inventory_status' => ['Les états loué/vendu sont gérés par les parcours de location et de vente.']]);
        }
    }

    public function assertPublishable(Vehicle $vehicle): void
    {
        if (! Shop::publiclyVisible()->whereKey($vehicle->shop_id)->exists()
            || ! $vehicle->images()->where('position', 0)->exists()
            || (! $vehicle->is_for_sale && ! $vehicle->is_for_rent)
            || ($vehicle->is_for_sale && (int) $vehicle->sale_price_minor <= 0)
            || ($vehicle->is_for_rent && (int) $vehicle->rent_daily_minor <= 0)) {
            throw ValidationException::withMessages(['publication_status' => ['Publication impossible : boutique/professionnel autorisés, prix et image principale requis.']]);
        }
    }
}
