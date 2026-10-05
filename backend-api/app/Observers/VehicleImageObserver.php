<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Services\RealtimePublisher;

class VehicleImageObserver
{
    public function saved(VehicleImage $image): void
    {
        $this->changed($image);
    }

    public function deleted(VehicleImage $image): void
    {
        $this->changed($image);
    }

    private function changed(VehicleImage $image): void
    {
        if ($vehicle = Vehicle::find($image->vehicle_id)) {
            app(RealtimePublisher::class)->vehicle($vehicle, 'VehicleImageUpdated', false, ['images']);
        }
    }
}
