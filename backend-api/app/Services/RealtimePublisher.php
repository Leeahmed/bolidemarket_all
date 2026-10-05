<?php

namespace App\Services;

use App\Events\Realtime as Events;
use App\Models\CommerceRecord;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\Vehicle;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RealtimePublisher
{
    // The callback is discarded on rollback, including a rolled-back nested transaction.
    // A socket outage must never turn an already committed REST write into a failure.
    public function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback) {
            try {
                $callback();
            } catch (\Throwable $error) {
                Log::warning('Realtime delivery unavailable; REST remains authoritative.', ['exception' => get_class($error)]);
            }
        });
    }

    public function vehicle(Vehicle $vehicle, string $event, bool $wasPublic = false, array $fields = []): void
    {
        $data = ['vehicle_id' => (string) $vehicle->id, 'slug' => $vehicle->slug, 'shop_id' => (string) $vehicle->shop_id,
            'version' => (int) $vehicle->version, 'changed_fields' => array_values(array_intersect($fields, [
                'negotiation_enabled', 'title', 'sale_price_minor', 'rent_daily_minor', 'inventory_status', 'publication_status', 'images', 'availability', 'shop',
                'description', 'year', 'mileage_km', 'category_id', 'is_for_sale', 'is_for_rent',
            ]))];
        $merchantId = Shop::withTrashed()->whereKey($vehicle->shop_id)->value('merchant_id');
        $this->afterCommit(function () use ($data, $merchantId, $event, $wasPublic) {
            $public = Vehicle::publiclyVisible()->whereKey($data['vehicle_id'])->exists();
            $class = 'App\\Events\\Realtime\\'.$event;
            if ($merchantId) {
                event(new $class([new PrivateChannel('merchant.'.$merchantId)], $data));
            }
            if ($public || $wasPublic) {
                $publicClass = ! $public ? Events\VehicleUnpublished::class : $class;
                event(new $publicClass([new Channel('marketplace')], $data));
            }
        });
    }

    public function commerce(CommerceRecord $record): void
    {
        $kind = $record instanceof Reservation ? 'Reservation' : 'Order';
        $status = $record->status->value;
        $suffix = ! $record->getRawOriginal('id') ? 'Created' : match ($status) {
            'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected',
            'active' => 'Started', 'completed', 'fulfilled' => 'Completed', 'expired' => 'Expired',
            default => 'Updated',
        };
        $class = 'App\\Events\\Realtime\\'.$kind.$suffix;
        $data = ['id' => (string) $record->id, 'shop_id' => (string) $record->shop_id,
            'vehicle_id' => (string) $record->vehicle_id, 'status' => $status, 'version' => (int) $record->version];
        $merchantId = $record->shop->merchant_id;
        $userId = $record->user_id;
        $this->afterCommit(fn () => event(new $class([new PrivateChannel('merchant.'.$merchantId), new PrivateChannel('user.'.$userId)], $data)));
        if ($vehicle = $record->vehicle) {
            $this->vehicle($vehicle, 'VehicleAvailabilityChanged', false, ['availability']);
        }
    }
}
