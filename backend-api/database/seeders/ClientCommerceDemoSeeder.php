<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ReservationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClientCommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Commerce démo interdit hors local/testing.');
        }
        $client = User::where('email', 'client@bolidemarket.demo')->where('role', UserRole::CLIENT)->whereNull('disabled_at')->firstOrFail();
        DB::transaction(function () use ($client) {
            // Two dedicated demo units keep the previously approved catalogue examples intact.
            foreach (['sale', 'rental'] as $kind) {
                $reference = 'BM-COMMERCE-DEMO-'.strtoupper($kind);
                $vehicle = Vehicle::where('reference', $reference)->first();
                if (! $vehicle) {
                    $source = Vehicle::publiclyVisible()->where('is_demo', true)->where('inventory_status', 'available')->where($kind === 'sale' ? 'is_for_sale' : 'is_for_rent', true)->with('images')->firstOrFail();
                    $vehicle = $source->replicate();
                    $vehicle->reference = $reference;
                    $vehicle->slug = 'commerce-demo-'.$kind;
                    $vehicle->title = $source->title.' — démonstration transaction';
                    $vehicle->description = 'Unité dédiée aux tests du parcours de démonstration. Aucune vente ni location réelle.';
                    $vehicle->is_for_sale = $kind === 'sale';
                    $vehicle->is_for_rent = $kind === 'rental';
                    $vehicle->sale_price_minor = $kind === 'sale' ? $source->sale_price_minor : null;
                    $vehicle->rent_daily_minor = $kind === 'rental' ? $source->rent_daily_minor : null;
                    $vehicle->save();
                    if ($photo = $source->images->first()) {
                        $key = 'vehicles/'.$vehicle->id.'/commerce-demo.'.pathinfo($photo->storage_key, PATHINFO_EXTENSION);
                        Storage::disk('public')->copy($photo->storage_key, $key);
                        $vehicle->images()->create(['storage_key' => $key, 'position' => 0, 'alt_text' => 'Démonstration uniquement', 'is_placeholder' => $photo->is_placeholder]);
                    }
                }
                if (! $vehicle->is_demo || ! $vehicle->shop->is_demo) {
                    throw new RuntimeException('Référence démo occupée par une ressource non démo.');
                }
                Favorite::firstOrCreate(['user_id' => $client->id, 'vehicle_id' => $vehicle->id]);
                $merchant = $vehicle->shop->merchant->owner;
                if ($kind === 'rental' && ! Reservation::where('vehicle_id', $vehicle->id)->exists()) {
                    $service = app(ReservationService::class);
                    $reservation = $service->create($client, ['vehicle_id' => $vehicle->id, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(13)->toDateString(), 'payment_method' => 'MOBILE_MONEY_DEMO']);
                    $service->transition($merchant, $reservation, ReservationStatus::CONFIRMED, true);
                }
                if ($kind === 'sale' && ! Order::where('vehicle_id', $vehicle->id)->exists()) {
                    $service = app(OrderService::class);
                    $order = $service->create($client, ['vehicle_id' => $vehicle->id, 'payment_method' => 'CASH_DEMO', 'handover' => ['mode' => 'self', 'scheduled_local' => now()->addDays(3)->startOfDay()->format('Y-m-d\\TH:i'), 'contact_name' => $client->name, 'contact_phone' => '+2250701020304', 'notes' => 'Coordonnées fictives de démonstration.']]);
                    $order = $service->transition($merchant, $order, OrderStatus::CONFIRMED, true);
                    $service->transition($merchant, $order, OrderStatus::FULFILLED, true);
                }
            }
        }, 3);
    }
}
