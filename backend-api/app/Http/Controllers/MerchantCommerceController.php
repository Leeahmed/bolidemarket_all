<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Http\Requests\Commerce\CommerceListRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReservationResource;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MerchantCommerceController extends Controller
{
    private function listing(CommerceListRequest $request, string $model)
    {
        $query = $model::forMerchant($request->user())->whereHas('shop', fn ($q) => $q->whereColumn('shops.is_demo', (new $model)->getTable().'.is_demo'));
        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->validated('shop_id'));
        }
        if ($model === Order::class && $request->filled('kind')) {
            $query->where('kind', $request->validated('kind'));
        }
        if ($model === Reservation::class && $request->boolean('rentals')) {
            $query->whereIn('status', ['active', 'completed']);
        }
        if ($text = $request->validated('q')) {
            $query->where(fn ($q) => $q->where('reference', 'like', '%'.$text.'%')
                ->orWhereHas('vehicle', fn ($v) => $v->where('title', 'like', '%'.$text.'%'))
                ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', '%'.$text.'%')->orWhere('last_name', 'like', '%'.$text.'%')->orWhere('email', 'like', '%'.$text.'%')));
        }
        if ($status = $request->validated('status')) {
            $terminal = $model === Reservation::class ? 'expired' : 'cancelled';
            if ($status === 'pending') {
                $query->where('status', 'pending')->where('expires_at', '>', now());
            } elseif ($status === $terminal) {
                $query->where(fn ($q) => $q->where('status', $terminal)->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('expires_at', '<=', now())));
            } else {
                $query->where('status', $status);
            }
        }

        return $query->with('customer')->with($model === Reservation::class ? 'order.payment' : 'payment')->orderByDesc('id')->paginate($request->pageSize());
    }

    public function reservations(CommerceListRequest $request)
    {
        return ReservationResource::collection($this->listing($request, Reservation::class));
    }

    public function orders(CommerceListRequest $request)
    {
        return OrderResource::collection($this->listing($request, Order::class));
    }

    public function reservation(Request $request, string $reservation)
    {
        $record = Reservation::forMerchant($request->user())->with('customer', 'order.payment')->findOrFail($reservation);
        Gate::authorize('manage', $record);

        return new ReservationResource($record);
    }

    public function order(Request $request, string $order)
    {
        $record = Order::forMerchant($request->user())->with('customer', 'payment')->findOrFail($order);
        Gate::authorize('manage', $record);

        return new OrderResource($record);
    }

    public function reservationAction(Request $request, string $reservation, ReservationService $service)
    {
        abort_unless($request->user()->canUseCommerce(), 403);
        $record = Reservation::forMerchant($request->user())->findOrFail($reservation);

        return new ReservationResource($service->transition($request->user(), $record, ReservationStatus::from($request->route('target')), true)->load('customer', 'order.payment'));
    }

    public function orderAction(Request $request, string $order, OrderService $service)
    {
        abort_unless($request->user()->canUseCommerce(), 403);
        $record = Order::forMerchant($request->user())->findOrFail($order);

        return new OrderResource($service->transition($request->user(), $record, OrderStatus::from($request->route('target')), true)->load('customer', 'payment'));
    }
}
