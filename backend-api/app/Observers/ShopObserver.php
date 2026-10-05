<?php

namespace App\Observers;

use App\Models\Shop;
use App\Models\Vehicle;
use App\Services\RealtimePublisher;

class ShopObserver
{
    public function saving(Shop $shop): void
    {
        $shop->publicVehicleIdsForBroadcast = $shop->exists ? Vehicle::publiclyVisible()->where('shop_id', $shop->id)->pluck('id')->all() : [];
    }

    public function deleting(Shop $shop): void
    {
        $this->saving($shop);
    }

    public function saved(Shop $shop): void
    {
        if ($shop->wasChanged()) {
            $this->changed($shop);
        }
    }

    public function deleted(Shop $shop): void
    {
        $this->changed($shop);
    }

    private function changed(Shop $shop): void
    {
        foreach (Vehicle::where('shop_id', $shop->id)->get() as $vehicle) {
            $wasPublic = in_array($vehicle->id, $shop->publicVehicleIdsForBroadcast);
            app(RealtimePublisher::class)->vehicle($vehicle, $wasPublic ? 'VehicleUpdated' : 'VehiclePublished', $wasPublic, ['shop']);
        }
    }
}
