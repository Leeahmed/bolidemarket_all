<?php

namespace App\Services\Commerce;

use App\Exceptions\CommerceConflict;
use App\Models\PriceOffer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RealtimePublisher;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PriceOfferService
{
    public function __construct(private AvailabilityService $availability) {}

    private function eligible(Vehicle $vehicle): void
    {
        $this->availability->assertOffer($vehicle, 'sale');
        if (! $vehicle->negotiation_enabled) {
            throw new CommerceConflict('NEGOTIATION_DISABLED', 'Le vendeur n’accepte pas de propositions pour cette annonce.');
        }
    }

    public function create(User $user, array $input): PriceOffer
    {
        return DB::transaction(function () use ($user, $input) {
            $v = $this->availability->lock((int) $input['vehicle_id']);
            $this->eligible($v);
            abort_if(Vehicle::managedBy($user)->whereKey($v->id)->exists(), 403);
            if ($input['currency'] !== $v->currency_code) {
                throw ValidationException::withMessages(['currency' => ['La devise doit être celle de l’annonce.']]);
            }
            if ((int) $input['amount_minor'] >= (int) $v->sale_price_minor) {
                throw ValidationException::withMessages(['amount_minor' => ['Proposez un montant positif inférieur au prix affiché.']]);
            }
            if (PriceOffer::where('vehicle_id', $v->id)->where('user_id', $user->id)->whereIn('status', ['pending', 'accepted'])->where('expires_at', '>', now())->exists()) {
                throw new CommerceConflict('OFFER_EXISTS', 'Vous avez déjà une proposition en cours pour ce véhicule.');
            }
            $offer = PriceOffer::create($this->availability->snapshots($v) + ['user_id' => $user->id, 'vehicle_id' => $v->id, 'shop_id' => $v->shop_id, 'amount_minor' => $input['amount_minor'], 'asking_price_minor' => $v->sale_price_minor, 'currency_code' => $v->currency_code, 'minor_unit' => $v->currency->minor_unit, 'vehicle_version' => $v->version, 'status' => 'pending', 'expires_at' => now()->addHours(24), 'is_demo' => $v->is_demo]);
            $this->signal($offer, 'PriceOfferCreated');

            return $offer;
        }, 3);
    }

    public function respond(User $user, PriceOffer $offer, string $decision): PriceOffer
    {
        return DB::transaction(function () use ($user, $offer, $decision) {
            $v = $this->availability->lock($offer->vehicle_id);
            Gate::forUser($user)->authorize('update', $v);
            $offer = PriceOffer::lockForUpdate()->findOrFail($offer->id);
            if ($offer->status !== 'pending' || $offer->expires_at->isPast()) {
                throw new CommerceConflict('OFFER_UNAVAILABLE', 'Cette proposition ne peut plus être traitée.');
            }
            if ($decision === 'accepted') {
                $this->eligible($v);
                if ((int) $offer->vehicle_version !== (int) $v->version) {
                    throw new CommerceConflict('OFFER_STALE', 'L’annonce a changé. Refusez cette proposition et invitez le client à en envoyer une nouvelle.');
                }
            }
            $offer->update(['status' => $decision, 'responded_at' => now(), 'expires_at' => now()->addHours(24)]);
            CommerceAudit::record($user, $offer, $decision);
            $this->signal($offer, $decision === 'accepted' ? 'PriceOfferAccepted' : 'PriceOfferRejected');

            return $offer;
        }, 3);
    }

    public function forPurchase(User $user, Vehicle $v, int $id): PriceOffer
    {
        $offer = PriceOffer::where('user_id', $user->id)->where('vehicle_id', $v->id)->lockForUpdate()->findOrFail($id);
        $this->eligible($v);
        if ($offer->status !== 'accepted' || $offer->expires_at->isPast() || (int) $offer->vehicle_version !== (int) $v->version || $offer->currency_code !== $v->currency_code) {
            throw new CommerceConflict('OFFER_UNAVAILABLE', 'Cette proposition n’est plus utilisable. Consultez son état avant d’acheter.');
        }

        return $offer;
    }

    public function signal(PriceOffer $offer, string $event): void
    {
        $data = ['id' => (string) $offer->id, 'shop_id' => (string) $offer->shop_id, 'vehicle_id' => (string) $offer->vehicle_id, 'status' => $offer->status];
        $channels = [new PrivateChannel('merchant.'.$offer->shop->merchant_id), new PrivateChannel('user.'.$offer->user_id)];
        app(RealtimePublisher::class)->afterCommit(function () use ($event, $channels, $data) {
            $class = 'App\\Events\\Realtime\\'.$event;
            event(new $class($channels, $data));
        });
    }
}
