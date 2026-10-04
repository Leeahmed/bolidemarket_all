<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\VehicleResource;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantDashboardController extends Controller
{
    private function shop(Request $request): Shop
    {
        $data = $request->validate(['shop_id' => ['required', 'integer', 'min:1']]);

        return Shop::managedBy($request->user())->findOrFail($data['shop_id']);
    }

    public function dashboard(Request $request)
    {
        $shop = $this->shop($request);

        // A single consistent MySQL snapshot for counts, charts and recent lists.
        return DB::transaction(function () use ($shop, $request) {
            $now = now();
            $vehicles = Vehicle::where('shop_id', $shop->id)->where('is_demo', $shop->is_demo);
            $reservations = Reservation::where('shop_id', $shop->id)->where('is_demo', $shop->is_demo);
            $orders = Order::where('shop_id', $shop->id)->where('is_demo', $shop->is_demo);
            $fleet = array_replace(['available' => 0, 'rented' => 0, 'sold' => 0, 'other' => 0],
                (clone $vehicles)->selectRaw('inventory_status, COUNT(*) AS total')->groupBy('inventory_status')->pluck('total', 'inventory_status')->map(fn ($n) => (int) $n)->all());
            $start = $now->copy()->startOfMonth()->subMonths(5);
            $sales = (clone $orders)->where('kind', 'sale')->where('status', 'fulfilled')->whereBetween('fulfilled_at', [$start, $now])
                ->selectRaw("DATE_FORMAT(fulfilled_at, '%Y-%m') AS month, COUNT(*) AS total")->groupBy('month')->pluck('total', 'month');
            $activity = [];
            for ($i = 0; $i < 6; $i++) {
                $month = $start->copy()->addMonths($i)->format('Y-m');
                $activity[] = ['month' => $month, 'sales' => (int) ($sales[$month] ?? 0)];
            }

            return ['data' => [
                'as_of' => $now->toISOString(), 'is_demo' => $shop->is_demo, 'shop_id' => (string) $shop->id,
                'fleet' => $fleet, 'vehicles_total' => array_sum($fleet),
                'pending_reservations' => (clone $reservations)->where('status', 'pending')->where('expires_at', '>', $now)->count(),
                'reservations_total' => (clone $reservations)->count(), 'orders_total' => (clone $orders)->where('kind', 'sale')->count(),
                'activity' => $activity,
                'recent_vehicles' => VehicleResource::collection((clone $vehicles)->with(Vehicle::PUBLIC_RELATIONS)->latest('id')->limit(5)->get())->resolve($request),
                'recent_reservations' => ReservationResource::collection((clone $reservations)->with('customer', 'order.payment')->latest('id')->limit(5)->get())->resolve($request),
                'recent_orders' => OrderResource::collection((clone $orders)->where('kind', 'sale')->with('customer', 'payment')->latest('id')->limit(5)->get())->resolve($request),
            ]];
        });
    }

    public function clients(Request $request)
    {
        $shop = $this->shop($request);
        $data = $request->validate(['q' => ['sometimes', 'string', 'max:120'], 'page' => ['sometimes', 'integer', 'min:1']]);
        // A rental order is the accounting counterpart of its reservation, not a second interaction.
        $operations = Reservation::where('shop_id', $shop->id)->where('is_demo', $shop->is_demo)->select('user_id', 'created_at')
            ->unionAll(Order::where('shop_id', $shop->id)->where('is_demo', $shop->is_demo)->where('kind', 'sale')->select('user_id', 'created_at'));
        $summary = DB::query()->fromSub($operations, 'operations')->selectRaw('user_id, COUNT(*) AS operations_count, MAX(created_at) AS last_activity')->groupBy('user_id');

        return User::query()->joinSub($summary, 'activity', fn ($join) => $join->on('users.id', '=', 'activity.user_id'))
            ->when($data['q'] ?? null, fn ($q, $text) => $q->where(fn ($q) => $q->where('first_name', 'like', '%'.$text.'%')->orWhere('last_name', 'like', '%'.$text.'%')->orWhere('email', 'like', '%'.$text.'%')))
            ->orderByDesc('last_activity')->orderBy('users.id')->paginate(20, ['users.id', 'first_name', 'last_name', 'email', 'phone', 'operations_count', 'last_activity'])
            ->through(fn ($user) => ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'operations_count' => (int) $user->operations_count, 'last_activity' => $user->last_activity]);
    }
}
