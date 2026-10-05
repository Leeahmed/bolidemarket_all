<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\PaginationRequest;
use App\Http\Resources\PriceOfferResource;
use App\Models\PriceOffer;
use App\Services\Commerce\IdempotencyService;
use App\Services\Commerce\PriceOfferService;
use Illuminate\Http\Request;

class PriceOfferController extends Controller
{
    public function store(Request $request, PriceOfferService $service, IdempotencyService $idempotency)
    {
        abort_unless($request->user()->canUseCommerce(), 403);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        $input = $request->validate(['vehicle_id' => 'required|integer|min:1', 'amount_minor' => 'required|integer|min:1|max:99999999999999', 'currency' => 'required|string|size:3', 'idempotency_key' => 'required|string|min:8|max:80|regex:/^[A-Za-z0-9_-]+$/']);
        [$offer,$replayed] = $idempotency->run($request->user(), 'price_offer', $input['idempotency_key'], $input, PriceOffer::class, fn () => $service->create($request->user(), $input));

        return (new PriceOfferResource($offer))->response()->setStatusCode($replayed ? 200 : 201);
    }

    public function mine(PaginationRequest $request)
    {
        $query = PriceOffer::forCustomer($request->user());
        if ($request->filled('vehicle_id')) {
            $input = $request->validate(['vehicle_id' => 'integer|min:1']);
            $query->where('vehicle_id', $input['vehicle_id']);
        }

        return PriceOfferResource::collection($query->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function show(Request $request, string $offer)
    {
        return new PriceOfferResource(PriceOffer::forCustomer($request->user())->findOrFail($offer));
    }

    public function merchant(PaginationRequest $request)
    {
        $query = PriceOffer::forMerchant($request->user())->with('customer');
        if ($request->filled('shop_id')) {
            $input = $request->validate(['shop_id' => 'integer|min:1']);
            $query->where('shop_id', $input['shop_id']);
        }

        return PriceOfferResource::collection($query->orderByDesc('id')->paginate($request->pageSize()));
    }

    public function respond(Request $request, string $offer, PriceOfferService $service)
    {
        abort_unless($request->user()->canUseCommerce(), 403);
        $input = $request->validate(['decision' => 'required|in:accepted,rejected']);
        $record = PriceOffer::forMerchant($request->user())->findOrFail($offer);

        return new PriceOfferResource($service->respond($request->user(), $record, $input['decision'])->load('customer'));
    }
}
