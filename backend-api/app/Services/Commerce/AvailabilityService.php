<?php

namespace App\Services\Commerce;

use App\Enums\InventoryStatus;
use App\Exceptions\CommerceConflict;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AvailabilityService
{
    public function assertDemo(Vehicle $vehicle): void
    {
        abort_unless(app()->environment(['local', 'testing']) && $vehicle->is_demo && $vehicle->shop?->is_demo, 403);
    }

    public function lock(int $id): Vehicle
    {
        return Vehicle::with(['shop', 'currency', 'vehicleModel.brand'])->lockForUpdate()->findOrFail($id);
    }

    public function assertOffer(Vehicle $vehicle, string $kind): void
    {
        $this->assertDemo($vehicle);
        if (! Vehicle::publiclyVisible()->whereKey($vehicle->id)->exists()
            || $vehicle->inventory_status !== InventoryStatus::AVAILABLE
            || ($kind === 'rental' ? ! $vehicle->is_for_rent : ! $vehicle->is_for_sale)
            || (int) ($kind === 'rental' ? $vehicle->rent_daily_minor : $vehicle->sale_price_minor) <= 0) {
            throw new CommerceConflict('VEHICLE_UNAVAILABLE', 'Ce véhicule ne peut pas être réservé ou acheté dans cet état.');
        }
    }

    public function dates(array $input, string $timezone): array
    {
        $start = isset($input['start_date']) ? CarbonImmutable::createFromFormat('!Y-m-d', $input['start_date'], $timezone) : CarbonImmutable::parse($input['starts_at']);
        $end = isset($input['end_date']) ? CarbonImmutable::createFromFormat('!Y-m-d', $input['end_date'], $timezone) : CarbonImmutable::parse($input['ends_at']);
        if ($start->setTimezone($timezone)->startOfDay()->lt(CarbonImmutable::now($timezone)->startOfDay()) || ! $end->gt($start)) {
            throw ValidationException::withMessages(['dates' => ['Dates invalides : début à partir d’aujourd’hui, fin strictement après le début.']]);
        }
        $start = $start->utc();
        $end = $end->utc();
        $days = (int) ceil(($end->timestamp - $start->timestamp) / 86400);
        if ($days > config('commerce.max_rental_days')) {
            throw ValidationException::withMessages(['dates' => ['La démonstration est limitée à 365 jours.']]);
        }

        return [$start, $end, max(1, $days)];
    }

    public function assertFree(Vehicle $vehicle, CarbonImmutable $start, ?CarbonImmutable $end, ?int $exceptBlock = null): void
    {
        $query = VehicleBlock::blocking()->where('vehicle_id', $vehicle->id)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $start));
        if ($end) {
            $query->where('starts_at', '<', $end);
        }
        if ($exceptBlock) {
            $query->where('id', '!=', $exceptBlock);
        }
        if ($query->lockForUpdate()->exists()) {
            throw new CommerceConflict('VEHICLE_UNAVAILABLE', 'Une autre allocation bloque le véhicule sur cette période.');
        }
    }

    public function expire(Vehicle $vehicle): void
    {
        // Parent vehicle must already be locked. Availability also ignores expired holds without this cleanup.
        $blocks = VehicleBlock::where('vehicle_id', $vehicle->id)->where('kind', 'hold')->whereNull('released_at')->where('expires_at', '<=', now())->lockForUpdate()->get();
        foreach ($blocks as $block) {
            if ($block->reservation_id) {
                Reservation::whereKey($block->reservation_id)->where('status', 'pending')->first()?->update(['status' => 'expired']);
            }
            if ($block->order_id) {
                Order::whereKey($block->order_id)->where('status', 'pending')->first()?->update(['status' => 'cancelled', 'cancellation_reason' => 'expired']);
            }
            $block->update(['released_at' => now()]);
        }
    }

    public function assertEditable(Vehicle $vehicle): void
    {
        if (Reservation::where('vehicle_id', $vehicle->id)->where('status', 'active')->exists()
            || VehicleBlock::blocking()->where('vehicle_id', $vehicle->id)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists()) {
            throw new CommerceConflict('VEHICLE_UNAVAILABLE', 'Le véhicule possède un engagement actif ; sa modification est bloquée.');
        }
    }

    public function snapshots(Vehicle $vehicle, ?User $buyer = null): array
    {
        return [
            'vehicle_snapshot' => ['id' => (string) $vehicle->id, 'reference' => $vehicle->reference, 'slug' => $vehicle->slug, 'title' => $vehicle->title, 'brand' => $vehicle->vehicleModel->brand->name, 'model' => $vehicle->vehicleModel->name, 'year' => $vehicle->year, 'trim' => $vehicle->trim, 'category' => $vehicle->category?->label],
            'seller_snapshot' => ['id' => (string) $vehicle->shop_id, 'name' => $vehicle->shop->name, 'slug' => $vehicle->shop->slug, 'address' => $vehicle->shop->address, 'timezone' => $vehicle->shop->timezone, 'email' => $vehicle->shop->email, 'phone' => $vehicle->shop->phone, 'country' => $vehicle->shop->country?->name, 'city' => $vehicle->shop->city?->name, 'district' => $vehicle->shop->district?->name],
            ...($buyer ? ['buyer_snapshot' => ['name' => $buyer->name, 'first_name' => $buyer->first_name, 'last_name' => $buyer->last_name, 'email' => $buyer->email, 'phone' => $buyer->phone, 'country' => $buyer->country?->name, 'city' => $buyer->city?->name]] : []),
        ];
    }
}
