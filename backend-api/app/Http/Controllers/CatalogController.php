<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\PaginationRequest;
use App\Http\Requests\Catalog\VehicleSearchRequest;
use App\Http\Resources\ShopResource;
use App\Http\Resources\VehicleResource;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Services\VehicleSearchService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function vehicles(VehicleSearchRequest $request, VehicleSearchService $search)
    {
        return VehicleResource::collection($search->search($request->validated()));
    }

    public function nearby(VehicleSearchRequest $request, VehicleSearchService $search)
    {
        return VehicleResource::collection($search->search($request->validated(), true));
    }

    public function filters(VehicleSearchService $search)
    {
        return response()->json(['data' => $search->facets()]);
    }

    public function suggestions(Request $request, VehicleSearchService $search)
    {
        $input = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);

        return response()->json(['data' => $search->suggestions($input['q'])]);
    }

    public function vehicle(string $slug)
    {
        return new VehicleResource(Vehicle::publiclyVisible()->with(Vehicle::PUBLIC_RELATIONS)->where('slug', $slug)->firstOrFail());
    }

    public function shops(PaginationRequest $request)
    {
        return ShopResource::collection(Shop::publiclyVisible()->with(Shop::PUBLIC_RELATIONS)
            ->withCount(['publicVehicles', 'publicVehicles as sale_vehicles_count' => fn ($q) => $q->where('is_for_sale', true),
                'publicVehicles as rental_vehicles_count' => fn ($q) => $q->where('is_for_rent', true)])
            ->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function shop(string $slug)
    {
        $shop = Shop::publiclyVisible()->with(Shop::PUBLIC_RELATIONS)
            ->withCount(['publicVehicles', 'publicVehicles as sale_vehicles_count' => fn ($q) => $q->where('is_for_sale', true),
                'publicVehicles as rental_vehicles_count' => fn ($q) => $q->where('is_for_rent', true)])
            ->where('slug', $slug)->firstOrFail();
        $vehicles = $shop->publicVehicles()->with(Vehicle::PUBLIC_RELATIONS)->orderByDesc('id')->limit(6)->get();
        // Do not attach the parent shop with recentVehicles to each child (recursive resource).
        $shop->setRelation('recentVehicles', $vehicles);

        return new ShopResource($shop);
    }

    public function shopVehicles(VehicleSearchRequest $request, string $slug, VehicleSearchService $search)
    {
        $shop = Shop::publiclyVisible()->where('slug', $slug)->firstOrFail();

        return VehicleResource::collection($search->search($request->validated(), false, $shop->id));
    }
}
