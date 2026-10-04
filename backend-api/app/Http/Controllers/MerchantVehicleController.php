<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\MerchantVehicleListRequest;
use App\Http\Requests\Catalog\StoreVehicleRequest;
use App\Http\Requests\Catalog\UpdateVehicleRequest;
use App\Http\Requests\Catalog\UpdateVehicleStatusRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Support\Facades\Gate;

class MerchantVehicleController extends Controller
{
    public function index(MerchantVehicleListRequest $request)
    {
        $query = Vehicle::managedBy($request->user())->whereHas('shop', fn ($q) => $q->whereColumn('shops.is_demo', 'vehicles.is_demo'));
        foreach (['shop_id', 'category_id', 'status' => 'inventory_status'] as $key => $column) {
            $key = is_int($key) ? $column : $key;
            if ($request->filled($key)) {
                $query->where($column, $request->validated($key));
            }
        }
        if ($request->filled('brand_id')) {
            $query->whereHas('vehicleModel', fn ($q) => $q->where('brand_id', $request->validated('brand_id')));
        }
        if ($request->filled('listing_type')) {
            $query->where($request->validated('listing_type') === 'sale' ? 'is_for_sale' : 'is_for_rent', true);
        }
        if ($text = $request->validated('q')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$text.'%')->orWhere('reference', 'like', '%'.$text.'%'));
        }

        return VehicleResource::collection($query->with(Vehicle::PUBLIC_RELATIONS)->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function store(StoreVehicleRequest $request, VehicleService $service)
    {
        return (new VehicleResource($service->save($request->user(), $request->validated())->load(Vehicle::PUBLIC_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle->load(Vehicle::PUBLIC_RELATIONS));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, VehicleService $service)
    {
        return new VehicleResource($service->save($request->user(), $request->validated(), $vehicle)->load(Vehicle::PUBLIC_RELATIONS));
    }

    public function status(UpdateVehicleStatusRequest $request, Vehicle $vehicle, VehicleService $service)
    {
        return new VehicleResource($service->changeStatus($vehicle, $request->validated())->load(Vehicle::PUBLIC_RELATIONS));
    }

    public function destroy(Vehicle $vehicle, VehicleService $service)
    {
        $service->delete($vehicle);

        return response()->noContent();
    }
}
