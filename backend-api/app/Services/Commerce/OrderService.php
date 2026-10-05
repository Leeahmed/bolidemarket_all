<?php

namespace App\Services\Commerce;

use App\Enums\BlockKind;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CommerceConflict;
use App\Models\Order;
use App\Models\User;
use App\Models\VehicleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private AvailabilityService $availability, private DemoPaymentService $payments) {}

    public function create(User $user, array $input): Order
    {
        return DB::transaction(function () use ($user, $input) {
            $vehicle = $this->availability->lock((int) $input['vehicle_id']);
            $this->availability->expire($vehicle);
            $this->availability->assertOffer($vehicle, 'sale');
            $handover = app(OrderHandover::class)->validate($input['handover'] ?? [], $user, $vehicle);
            $offer = isset($input['price_offer_id']) ? app(PriceOfferService::class)->forPurchase($user, $vehicle, (int) $input['price_offer_id']) : null;
            $price = $offer?->amount_minor ?? $vehicle->sale_price_minor;
            if ((isset($input['expected_price_minor']) && (string) $input['expected_price_minor'] !== (string) $price)
                || (isset($input['currency']) && $input['currency'] !== $vehicle->currency_code)) {
                throw new CommerceConflict('PRICE_CHANGED', 'Le prix a changé ; relisez le tarif avant de réessayer.');
            }
            $this->availability->assertFree($vehicle, CarbonImmutable::now(), null);
            $expires = now()->addMinutes(config('commerce.hold_minutes'));
            $order = Order::create($this->availability->snapshots($vehicle, $user) + ['reference' => 'BM-ORD-'.Str::ulid(), 'user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'shop_id' => $vehicle->shop_id,
                'kind' => 'sale', 'status' => OrderStatus::PENDING, 'subtotal_minor' => $price, 'fees_minor' => 0, 'total_minor' => $price, 'price_offer_id' => $offer?->id, 'handover' => $handover,
                'currency_code' => $vehicle->currency_code, 'minor_unit' => $vehicle->currency->minor_unit, 'payment_method_demo' => $input['payment_method'],
                'conditions_version' => config('commerce.conditions_version'), 'expires_at' => $expires, 'is_demo' => true]);
            if ($offer) {
                $offer->update(['status' => 'consumed']);
                app(PriceOfferService::class)->signal($offer, 'PriceOfferConsumed');
            }
            VehicleBlock::create(['vehicle_id' => $vehicle->id, 'order_id' => $order->id, 'kind' => BlockKind::HOLD, 'starts_at' => now(), 'expires_at' => $expires]);
            CommerceAudit::record($user, $order, 'created');

            return $order;
        }, 3);
    }

    public function transition(User $actor, Order $order, OrderStatus $target, bool $merchant = false): Order
    {
        return DB::transaction(function () use ($actor, $order, $target, $merchant) {
            $vehicle = $this->availability->lock($order->vehicle_id);
            $this->availability->assertDemo($vehicle);
            $this->availability->expire($vehicle);
            $order = Order::lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize($merchant ? 'manage' : 'cancel', $order);
            if (! $merchant && $target !== OrderStatus::CANCELLED) {
                abort(403);
            }
            if ($order->kind !== 'sale' || ! $order->status->allows($target)) {
                throw new CommerceConflict('INVALID_TRANSITION', 'Transition impossible ; une commande location suit sa réservation.');
            }
            $block = VehicleBlock::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($target === OrderStatus::CONFIRMED || $target === OrderStatus::FULFILLED) {
                $this->availability->assertOffer($vehicle, 'sale');
                $this->availability->assertFree($vehicle, CarbonImmutable::now(), null, $block->id);
            }
            if ($target === OrderStatus::CONFIRMED) {
                $this->payments->pay($order, $actor);
                $block->update(['kind' => BlockKind::SALE, 'expires_at' => null]);
                $order->confirmed_at = now();
                $order->expires_at = null;
            } elseif ($target === OrderStatus::FULFILLED) {
                if (! $order->payment()->where('is_demo', true)->where('status', 'paid')->exists()) {
                    throw new CommerceConflict('PAYMENT_REQUIRED', 'Paiement de démonstration manquant.');
                }
                $order->fulfilled_at = now();
                $vehicle->inventory_status = InventoryStatus::SOLD;
                $vehicle->version++;
                $vehicle->save();
            } else {
                $block->update(['released_at' => now()]);
                $order->cancellation_reason = 'demo_cancelled';
            }
            $order->status = $target;
            $order->version++;
            $order->save();
            CommerceAudit::record($actor, $order, $target->value);

            return $order;
        }, 3);
    }
}
