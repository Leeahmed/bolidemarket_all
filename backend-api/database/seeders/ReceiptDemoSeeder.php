<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ReservationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReceiptDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Reçus de démonstration interdits ici.');
        }
        $client = User::where('email', 'client@bolidemarket.demo')->firstOrFail();
        foreach ([['SALE-XOF', 'sale', 'XOF'], ['SALE-EUR', 'sale', 'EUR'], ['RENTAL-XOF-A', 'rental', 'XOF'], ['RENTAL-XOF-B', 'rental', 'XOF']] as [$key,$type,$currency]) {
            DB::transaction(function () use ($client, $key, $type, $currency) {
                $ref = 'BM-RECEIPT-DEMO-'.$key;
                $v = Vehicle::where('reference', $ref)->first();
                if (! $v) {
                    $source = Vehicle::publiclyVisible()->where('is_demo', true)->where('currency_code', $currency)->where($type === 'sale' ? 'is_for_sale' : 'is_for_rent', true)->with('images')->firstOrFail();
                    $v = $source->replicate();
                    $v->reference = $ref;
                    $v->slug = strtolower($ref);
                    $v->title = $source->title.' - reçu de démonstration';
                    $v->inventory_status = 'available';
                    $v->negotiation_enabled = false;
                    $v->save();
                    foreach ($source->images->take(1) as $image) {
                        $path = 'vehicles/'.$v->id.'/receipt-demo.'.pathinfo($image->storage_key, PATHINFO_EXTENSION);
                        Storage::disk('public')->copy($image->storage_key, $path);
                        $v->images()->create(['storage_key' => $path, 'position' => 0, 'alt_text' => 'Véhicule de démonstration']);
                    }
                }
                if (! $v->is_demo || ! $v->shop->is_demo) {
                    throw new RuntimeException('Référence occupée hors démonstration.');
                }
                if (Receipt::whereHas('order', fn ($q) => $q->where('vehicle_id', $v->id))->exists()) {
                    return;
                }
                $merchant = $v->shop->merchant->owner;
                if ($type === 'sale') {
                    $s = app(OrderService::class);
                    $o = Order::where('vehicle_id', $v->id)->first() ?? $s->create($client, ['vehicle_id' => $v->id, 'payment_method' => 'CARD_DEMO', 'handover' => ['mode' => 'self', 'scheduled_local' => now()->addDays(3)->startOfDay()->format('Y-m-d\TH:i'), 'contact_name' => 'Client démonstration', 'contact_phone' => '+2250701020304']]);
                    if ($o->status === OrderStatus::PENDING) {
                        $s->transition($merchant, $o, OrderStatus::CONFIRMED, true);
                    }
                } else {
                    $s = app(ReservationService::class);
                    $r = Reservation::where('vehicle_id', $v->id)->first() ?? $s->create($client, ['vehicle_id' => $v->id, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(14)->toDateString(), 'payment_method' => 'MOBILE_MONEY_DEMO']);
                    if ($r->status === ReservationStatus::PENDING) {
                        $s->transition($merchant, $r, ReservationStatus::CONFIRMED, true);
                    }
                }
            }, 3);
        }
    }
}
