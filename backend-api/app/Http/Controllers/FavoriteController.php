<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\PaginationRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Favorite;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FavoriteController extends Controller
{
    public function index(PaginationRequest $request)
    {
        return VehicleResource::collection(Vehicle::publiclyVisible()->whereIn('id', Favorite::where('user_id', $request->user()->id)->select('vehicle_id'))
            ->with(Vehicle::PUBLIC_RELATIONS)->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function store(Request $request, string $vehicle)
    {
        $vehicle = Vehicle::publiclyVisible()->with(Vehicle::PUBLIC_RELATIONS)->findOrFail($vehicle);
        Favorite::firstOrCreate(['user_id' => $request->user()->id, 'vehicle_id' => $vehicle->id]);

        return new VehicleResource($vehicle);
    }

    public function destroy(Request $request, string $vehicle)
    {
        $favorite = Favorite::where('user_id', $request->user()->id)->where('vehicle_id', $vehicle)->first();
        if ($favorite) {
            Gate::authorize('delete', $favorite);
            $favorite->delete();
        }

        return response()->noContent();
    }
}
