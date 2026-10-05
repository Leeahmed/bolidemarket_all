<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\RealtimePublisher;

class VehicleObserver
{
    public function saving(Vehicle $vehicle): void
    {
        $vehicle->wasPublicForBroadcast = $vehicle->exists && Vehicle::publiclyVisible()->whereKey($vehicle->id)->exists();
    }

    public function deleting(Vehicle $vehicle): void
    {
        $this->saving($vehicle);
    }

    public function saved(Vehicle $vehicle): void
    {
        $created = ! $vehicle->getRawOriginal('id');
        if (! $created && ! $vehicle->wasChanged()) {
            return;
        }
        $event = $created ? 'VehicleCreated' : ($vehicle->wasChanged('inventory_status') ? 'VehicleStatusChanged' : 'VehicleUpdated');
        if ($vehicle->wasChanged('publication_status')) {
            $event = $vehicle->publication_status->value === 'published' ? 'VehiclePublished' : 'VehicleUnpublished';
        }
        app(RealtimePublisher::class)->vehicle($vehicle, $event, $vehicle->wasPublicForBroadcast, array_keys($vehicle->getChanges()));
    }

    public function deleted(Vehicle $vehicle): void
    {
        app(RealtimePublisher::class)->vehicle($vehicle, 'VehicleUnpublished', $vehicle->wasPublicForBroadcast);
    }
}
