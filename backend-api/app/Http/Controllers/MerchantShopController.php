<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\PaginationRequest;
use App\Http\Requests\Catalog\ShopRequest;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Services\Commerce\AvailabilityService;
use App\Services\ShopService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MerchantShopController extends Controller
{
    public function index(PaginationRequest $request)
    {
        return ShopResource::collection(Shop::managedBy($request->user())->with(Shop::PUBLIC_RELATIONS)->withCount('publicVehicles')->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function store(ShopRequest $request, ShopService $service)
    {
        return (new ShopResource($service->save($request->user(), $request->validated())->load(Shop::PUBLIC_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Shop $shop)
    {
        Gate::authorize('view', $shop);

        return new ShopResource($shop->load(Shop::PUBLIC_RELATIONS)->loadCount('publicVehicles'));
    }

    public function update(ShopRequest $request, Shop $shop, ShopService $service)
    {
        return new ShopResource($service->save($request->user(), $request->validated(), $shop)->load(Shop::PUBLIC_RELATIONS));
    }

    public function destroy(Shop $shop)
    {
        Gate::authorize('delete', $shop);
        DB::transaction(function () use ($shop) {
            foreach (Vehicle::where('shop_id', $shop->id)->orderBy('id')->lockForUpdate()->get() as $vehicle) {
                app(AvailabilityService::class)->assertEditable($vehicle);
            }
            $shop->delete();
        }, 3);

        return response()->noContent();
    }
}
