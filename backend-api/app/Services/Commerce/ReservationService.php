<?php

namespace App\Services\Commerce;

use App\Enums\BlockKind;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\CommerceConflict;
use App\Models\Order;
use App\Models\RentalQuote;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(private AvailabilityService $availability, private DemoPaymentService $payments) {}

    public function quote(User $user, array $input): RentalQuote
    {
        return DB::transaction(function () use ($user, $input) {
            $vehicle = $this->availability->lock((int) $input['vehicle_id']);

            return $this->makeQuote($user, $vehicle, $input);
        }, 3);
    }

    private function makeQuote(User $user, Vehicle $vehicle, array $input): RentalQuote
    {
        $this->availability->assertOffer($vehicle, 'rental');
        [$start, $end, $days] = $this->availability->dates($input, $vehicle->shop->timezone);
        $this->availability->assertFree($vehicle, $start, $end);
        $total = (int) $vehicle->rent_daily_minor * $days;
        if ($total > 99999999999999) {
            throw new CommerceConflict('AMOUNT_LIMIT', 'Le montant dépasse la limite de démonstration.');
        }

        return RentalQuote::create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'starts_at' => $start, 'ends_at' => $end,
            'shop_timezone' => $vehicle->shop->timezone, 'billable_days' => $days, 'daily_price_minor' => $vehicle->rent_daily_minor,
            'total_minor' => $total, 'currency_code' => $vehicle->currency_code, 'minor_unit' => $vehicle->currency->minor_unit,
            'vehicle_version' => $vehicle->version, 'expires_at' => now()->addMinutes(config('commerce.quote_minutes'))]);
    }

    public function create(User $user, array $input): Reservation
    {
        return DB::transaction(function () use ($user, $input) {
            $quote = isset($input['quote_id']) ? RentalQuote::where('user_id', $user->id)->findOrFail($input['quote_id']) : null;
            $vehicle = $this->availability->lock((int) ($quote?->vehicle_id ?? $input['vehicle_id']));
            $this->availability->expire($vehicle);
            $this->availability->assertOffer($vehicle, 'rental');
            if ($quote) {
                $quote = RentalQuote::lockForUpdate()->findOrFail($quote->id);
                if ($quote->consumed_at || ! $quote->expires_at->isFuture()) {
                    throw new CommerceConflict('QUOTE_EXPIRED', 'Le devis a expiré ou a déjà été utilisé.');
                }
                if ($quote->vehicle_version !== $vehicle->version || $quote->daily_price_minor !== $vehicle->rent_daily_minor || $quote->currency_code !== $vehicle->currency_code) {
                    throw new CommerceConflict('PRICE_CHANGED', 'Le véhicule ou son tarif a changé. Demandez un nouveau devis.');
                }
                $this->availability->dates(['starts_at' => $quote->starts_at, 'ends_at' => $quote->ends_at], $quote->shop_timezone);
            } else {
                $quote = $this->makeQuote($user, $vehicle, $input);
            }
            $this->availability->assertFree($vehicle, $quote->starts_at, $quote->ends_at);
            $expires = now()->addMinutes(config('commerce.hold_minutes'));
            $reservation = Reservation::create($this->availability->snapshots($vehicle) + [
                'reference' => 'BM-RSV-'.Str::ulid(), 'user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'shop_id' => $vehicle->shop_id, 'quote_id' => $quote->id,
                'starts_at' => $quote->starts_at, 'ends_at' => $quote->ends_at, 'shop_timezone' => $quote->shop_timezone,
                'daily_price_minor' => $quote->daily_price_minor, 'billable_days' => $quote->billable_days, 'subtotal_minor' => $quote->total_minor, 'fees_minor' => 0, 'total_minor' => $quote->total_minor,
                'currency_code' => $quote->currency_code, 'minor_unit' => $quote->minor_unit, 'status' => ReservationStatus::PENDING, 'expires_at' => $expires,
                'payment_method_demo' => $input['payment_method'], 'conditions_version' => config('commerce.conditions_version'), 'is_demo' => true,
            ]);
            VehicleBlock::create(['vehicle_id' => $vehicle->id, 'reservation_id' => $reservation->id, 'kind' => BlockKind::HOLD, 'starts_at' => $quote->starts_at, 'ends_at' => $quote->ends_at, 'expires_at' => $expires]);
            $quote->update(['consumed_at' => now()]);
            CommerceAudit::record($user, $reservation, 'created');

            return $reservation;
        }, 3);
    }

    public function transition(User $actor, Reservation $reservation, ReservationStatus $target, bool $merchant = false): Reservation
    {
        return DB::transaction(function () use ($actor, $reservation, $target, $merchant) {
            $vehicle = $this->availability->lock($reservation->vehicle_id);
            $this->availability->assertDemo($vehicle);
            $this->availability->expire($vehicle);
            $reservation = Reservation::lockForUpdate()->findOrFail($reservation->id);
            Gate::forUser($actor)->authorize($merchant ? 'manage' : 'cancel', $reservation);
            if (! $merchant && $target !== ReservationStatus::CANCELLED) {
                abort(403);
            }
            if (! $reservation->status->allows($target)) {
                throw new CommerceConflict('INVALID_TRANSITION', 'Transition de réservation impossible.');
            }
            if ($target === ReservationStatus::CANCELLED && $reservation->status === ReservationStatus::CONFIRMED && ! $reservation->starts_at->isFuture()) {
                throw new CommerceConflict('INVALID_TRANSITION', 'Annulation démo autorisée uniquement avant le départ.');
            }
            $block = VehicleBlock::where('reservation_id', $reservation->id)->lockForUpdate()->firstOrFail();
            if ($target === ReservationStatus::CONFIRMED) {
                $this->availability->assertOffer($vehicle, 'rental');
                if (! $reservation->ends_at->isFuture()) {
                    throw new CommerceConflict('INVALID_TRANSITION', 'La période est terminée.');
                }
                $this->availability->assertFree($vehicle, $reservation->starts_at, $reservation->ends_at, $block->id);
                $order = Order::create(['reference' => 'BM-ORD-'.Str::ulid(), 'user_id' => $reservation->user_id, 'vehicle_id' => $vehicle->id, 'shop_id' => $reservation->shop_id, 'reservation_id' => $reservation->id,
                    'kind' => 'rental', 'status' => OrderStatus::CONFIRMED, 'subtotal_minor' => $reservation->subtotal_minor, 'fees_minor' => 0, 'total_minor' => $reservation->total_minor,
                    'currency_code' => $reservation->currency_code, 'minor_unit' => $reservation->minor_unit, 'payment_method_demo' => $reservation->payment_method_demo,
                    'vehicle_snapshot' => $reservation->vehicle_snapshot, 'seller_snapshot' => $reservation->seller_snapshot,
                    'conditions_version' => $reservation->conditions_version, 'confirmed_at' => now(), 'is_demo' => true]);
                $this->payments->pay($order, $actor);
                $block->update(['kind' => BlockKind::RENTAL, 'expires_at' => null]);
                $reservation->expires_at = null;
            } elseif ($target === ReservationStatus::ACTIVE) {
                $this->availability->assertOffer($vehicle, 'rental');
                if ($reservation->starts_at->isFuture() || ! $reservation->ends_at->isFuture()) {
                    throw new CommerceConflict('INVALID_TRANSITION', 'La location ne peut démarrer qu’au cours de sa période.');
                }
                $vehicle->inventory_status = InventoryStatus::RENTED;
            } elseif ($target === ReservationStatus::COMPLETED) {
                $vehicle->inventory_status = InventoryStatus::AVAILABLE;
                $block->update(['released_at' => now()]);
                $reservation->order()->update(['status' => OrderStatus::FULFILLED->value, 'fulfilled_at' => now()]);
            } else {
                $block->update(['released_at' => now()]);
                $reservation->order()->update(['status' => OrderStatus::CANCELLED->value, 'cancellation_reason' => 'reservation_cancelled']);
            }
            if ($vehicle->isDirty()) {
                $vehicle->version++;
                $vehicle->save();
            }
            $reservation->status = $target;
            $reservation->version++;
            $reservation->save();
            CommerceAudit::record($actor, $reservation, $target->value);

            return $reservation;
        }, 3);
    }
}
