<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Http\Requests\Catalog\PaginationRequest;
use App\Http\Requests\Commerce\RentalQuoteRequest;
use App\Http\Requests\Commerce\StoreOrderRequest;
use App\Http\Requests\Commerce\StoreReservationRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RentalQuoteResource;
use App\Http\Resources\ReservationResource;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\Commerce\IdempotencyService;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientCommerceController extends Controller
{
    public function quote(RentalQuoteRequest $request, ReservationService $service)
    {
        return (new RentalQuoteResource($service->quote($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function reserve(StoreReservationRequest $request, ReservationService $service, IdempotencyService $idempotency)
    {
        $input = $request->validated();
        [$record, $replayed] = $idempotency->run($request->user(), 'reservation', $input['idempotency_key'], $input, Reservation::class, fn () => $service->create($request->user(), $input));

        return (new ReservationResource($record->load('order.payment')))->response()->setStatusCode($replayed ? 200 : 201);
    }

    public function order(StoreOrderRequest $request, OrderService $service, IdempotencyService $idempotency)
    {
        $input = $request->validated();
        [$record, $replayed] = $idempotency->run($request->user(), 'order', $input['idempotency_key'], $input, Order::class, fn () => $service->create($request->user(), $input));

        return (new OrderResource($record->load('payment')))->response()->setStatusCode($replayed ? 200 : 201);
    }

    public function reservations(PaginationRequest $request)
    {
        return ReservationResource::collection(Reservation::forCustomer($request->user())->with('order.payment')->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function reservation(Request $request, string $reservation)
    {
        $record = Reservation::forCustomer($request->user())->with('order.payment')->findOrFail($reservation);
        Gate::authorize('view', $record);

        return new ReservationResource($record);
    }

    public function cancelReservation(Request $request, string $reservation, ReservationService $service)
    {
        $record = Reservation::forCustomer($request->user())->findOrFail($reservation);

        return new ReservationResource($service->transition($request->user(), $record, ReservationStatus::CANCELLED)->load('order.payment'));
    }

    public function orders(PaginationRequest $request)
    {
        return OrderResource::collection(Order::forCustomer($request->user())->with('payment')->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function showOrder(Request $request, string $order)
    {
        $record = Order::forCustomer($request->user())->with('payment')->findOrFail($order);
        Gate::authorize('view', $record);

        return new OrderResource($record);
    }

    public function cancelOrder(Request $request, string $order, OrderService $service)
    {
        $record = Order::forCustomer($request->user())->findOrFail($order);

        return new OrderResource($service->transition($request->user(), $record, OrderStatus::CANCELLED)->load('payment'));
    }
}
