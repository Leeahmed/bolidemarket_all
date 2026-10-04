<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreVehicleImageRequest;
use App\Http\Resources\VehicleImageResource;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Services\VehicleImages;
use Illuminate\Support\Facades\Gate;

class MerchantImageController extends Controller
{
    public function store(StoreVehicleImageRequest $request, Vehicle $vehicle, VehicleImages $service)
    {
        return (new VehicleImageResource($service->add($vehicle, $request->file('image'), $request->validated('alt_text'))))->response()->setStatusCode(201);
    }

    public function primary(Vehicle $vehicle, VehicleImage $image, VehicleImages $service)
    {
        Gate::authorize('update', $vehicle);
        abort_unless($image->vehicle_id === $vehicle->id, 404);

        return new VehicleImageResource($service->primary($vehicle, $image));
    }

    public function destroy(Vehicle $vehicle, VehicleImage $image, VehicleImages $service)
    {
        Gate::authorize('update', $vehicle);
        abort_unless($image->vehicle_id === $vehicle->id, 404);
        $service->delete($vehicle, $image);

        return response()->noContent();
    }
}
