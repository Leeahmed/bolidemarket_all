<?php

namespace App\Http\Controllers;

use App\Http\Requests\Commerce\AvailabilityRequest;
use App\Models\Vehicle;
use App\Models\VehicleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    public function show(AvailabilityRequest $request, string $slug)
    {
        $vehicle = Vehicle::publiclyVisible()->with('shop')->where('slug', $slug)->firstOrFail();
        $from = $request->filled('from') ? CarbonImmutable::parse($request->validated('from'), $vehicle->shop->timezone)->utc() : CarbonImmutable::now();
        $to = $request->filled('to') ? CarbonImmutable::parse($request->validated('to'), $vehicle->shop->timezone)->utc() : $from->addYear();
        if ($to->lte($from) || $to->gt($from->addYear())) {
            throw ValidationException::withMessages(['to' => ['La fenêtre doit être positive et limitée à un an.']]);
        }
        $blocks = VehicleBlock::blocking()->where('vehicle_id', $vehicle->id)->where('starts_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $from))->orderBy('starts_at')->paginate($request->pageSize());

        return response()->json(['data' => ['vehicle_id' => (string) $vehicle->id, 'inventory_status' => $vehicle->inventory_status->value,
            'is_for_rent' => $vehicle->is_for_rent, 'is_for_sale' => $vehicle->is_for_sale, 'timezone' => $vehicle->shop->timezone,
            'from' => $from->toISOString(), 'to' => $to->toISOString(), 'is_available_in_window' => $vehicle->inventory_status->value === 'available' && $blocks->total() === 0,
            'intervals' => $blocks->map(fn ($b) => ['starts_at' => $b->starts_at->toISOString(), 'ends_at' => $b->ends_at?->toISOString(), 'expires_at' => $b->expires_at?->toISOString()])],
            'meta' => ['current_page' => $blocks->currentPage(), 'last_page' => $blocks->lastPage(), 'total' => $blocks->total(), 'per_page' => $blocks->perPage()]]);
    }
}
