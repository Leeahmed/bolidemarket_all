<?php

namespace App\Services;

use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\ShopResource;
use App\Http\Resources\VehicleResource;
use App\Models\AdminActivityLog;
use App\Models\Favorite;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminReadService
{
    public const TYPES = ['users', 'merchants', 'shops', 'vehicles', 'reservations', 'orders', 'payments', 'receipts', 'activity'];

    public function query(string $type): Builder
    {
        return match ($type) {
            'users' => User::with(['country', 'city']),
            'merchants' => MerchantProfile::with(['owner.country', 'owner.city'])->withCount('shops')
                ->addSelect(['vehicles_count' => Vehicle::selectRaw('COUNT(*)')->whereHas('shop', fn ($q) => $q->whereColumn('merchant_id', 'merchant_profiles.id')),
                    'sales_count' => Order::selectRaw('COUNT(*)')->where('kind', 'sale')->whereHas('shop', fn ($q) => $q->whereColumn('merchant_id', 'merchant_profiles.id')),
                    'rentals_count' => Reservation::selectRaw('COUNT(*)')->whereHas('shop', fn ($q) => $q->whereColumn('merchant_id', 'merchant_profiles.id')),
                    'shop_names' => Shop::selectRaw("GROUP_CONCAT(name SEPARATOR ' · ')")->whereColumn('merchant_id', 'merchant_profiles.id'),
                    'shop_countries' => Shop::selectRaw('GROUP_CONCAT(DISTINCT country_code)')->whereColumn('merchant_id', 'merchant_profiles.id')]),
            'shops' => Shop::with([...Shop::PUBLIC_RELATIONS, 'merchant.owner.country', 'merchant.owner.city'])->withCount(['vehicles',
                'vehicles as sale_vehicles_count' => fn ($q) => $q->where('is_for_sale', true),
                'vehicles as rental_vehicles_count' => fn ($q) => $q->where('is_for_rent', true)]),
            'vehicles' => Vehicle::with(Vehicle::PUBLIC_RELATIONS),
            'reservations' => Reservation::with(['customer.country', 'customer.city', 'shop.merchant.owner.country', 'shop.merchant.owner.city', 'order.payment']),
            'orders' => Order::where('kind', 'sale')->with(['customer.country', 'customer.city', 'shop.merchant.owner.country', 'shop.merchant.owner.city', 'payment']),
            'payments' => Payment::where('is_demo', true)->with('order'),
            'receipts' => Receipt::query(),
            'activity' => AdminActivityLog::with('admin.country', 'admin.city'),
        };
    }

    public function filtered(string $type, array $p): Builder
    {
        $q = $this->query($type);
        if (! empty($p['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $p['q']).'%';
            $q->where(function ($b) use ($type, $term) {
                if ($type === 'users') {
                    $b->where('email', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term);
                } elseif ($type === 'merchants') {
                    $b->where('display_name', 'like', $term)->orWhere('legal_name', 'like', $term)->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $term));
                } elseif ($type === 'shops') {
                    $b->where('name', 'like', $term)->orWhere('slug', 'like', $term);
                } elseif ($type === 'activity') {
                    $b->where('action', 'like', $term)->orWhere('reason', 'like', $term);
                } else {
                    $b->where('reference', 'like', $term);
                    if ($type === 'vehicles') {
                        $b->orWhere('title', 'like', $term);
                    }
                }
            });
        }
        foreach (['country_code', 'city_id'] as $field) {
            if (isset($p[$field])) {
                if (in_array($type, ['users', 'shops', 'vehicles'])) {
                    $q->where($field, $p[$field]);
                } elseif ($type === 'merchants') {
                    $q->where(fn ($m) => $m->whereHas('owner', fn ($o) => $o->where($field, $p[$field]))
                        ->orWhereHas('shops', fn ($s) => $s->where($field, $p[$field])));
                } else {
                    $relation = $type === 'payments' ? 'order.shop' : 'shop';
                    $q->whereHas($relation, fn ($s) => $s->where($field, $p[$field]));
                }
            }
        }
        if (isset($p['shop_id'])) {
            $type === 'payments' ? $q->whereHas('order', fn ($o) => $o->where('shop_id', $p['shop_id'])) : $q->where('shop_id', $p['shop_id']);
        }
        if (isset($p['merchant_id'])) {
            $type === 'shops' ? $q->where('merchant_id', $p['merchant_id']) : $q->whereHas('shop', fn ($s) => $s->where('merchant_id', $p['merchant_id']));
        }
        if (isset($p['user_id'])) {
            $q->where('user_id', $p['user_id']);
        }
        if (isset($p['vehicle_id'])) {
            $q->where('vehicle_id', $p['vehicle_id']);
        }
        if (isset($p['role'])) {
            $q->where('role', $p['role']);
        }
        if (isset($p['status'])) {
            if ($type === 'users') {
                $p['status'] === 'active' ? $q->whereNull('disabled_at') : $q->whereNotNull('disabled_at');
            } else {
                $q->where(match ($type) {
                    'merchants' => 'approval_status', 'vehicles' => 'inventory_status', default => 'status'
                }, $p['status']);
            }
        }
        if (isset($p['publication_status'])) {
            $q->where('publication_status', $p['publication_status']);
        }
        if (isset($p['moderation_status'])) {
            $q->where('moderation_status', $p['moderation_status']);
        }
        if (isset($p['listing_type'])) {
            $type === 'vehicles' ? $q->where($p['listing_type'] === 'sale' ? 'is_for_sale' : 'is_for_rent', true) : $q->where('type', $p['listing_type']);
        }
        if (isset($p['brand_id'])) {
            $q->whereHas('vehicleModel', fn ($m) => $m->where('brand_id', $p['brand_id']));
        }
        if (isset($p['category_id'])) {
            $q->where('category_id', $p['category_id']);
        }

        return $q;
    }

    public function dto(string $type, $record, Request $request): array
    {
        $base = ['id' => (string) $record->id, 'created_at' => $record->created_at?->toISOString()];

        return $base + match ($type) {
            'users' => $this->user($record),
            'merchants' => ['name' => $record->display_name, 'legal_name' => $record->legal_name,
                'owner' => $this->user($record->owner), 'status' => $record->approval_status->value,
                'verified_at' => $record->verified_at?->toISOString(), 'shops_count' => $record->shops_count,
                'country_code' => $record->owner->country_code ?? $record->shop_countries, 'email' => $record->owner->email,
                'vehicles_count' => (int) $record->vehicles_count, 'sales_count' => (int) $record->sales_count, 'rentals_count' => (int) $record->rentals_count, 'shop_names' => $record->shop_names],
            'shops' => (new ShopResource($record))->resolve($request) + ['vehicles_count' => $record->vehicles_count, 'owner' => $this->user($record->merchant->owner)],
            'vehicles' => (new VehicleResource($record))->resolve($request) + ['moderation_status' => $record->moderation_status, 'version' => $record->version, 'vin' => $record->vin, 'license_plate' => $record->license_plate],
            'reservations' => (new ReservationResource($record))->resolve($request) + ['buyer' => $record->buyer_snapshot, 'client' => $this->user($record->customer), 'merchant' => $this->user($record->shop->merchant->owner)],
            'orders' => (new OrderResource($record))->resolve($request) + ['buyer' => $record->buyer_snapshot, 'client' => $this->user($record->customer), 'merchant' => $this->user($record->shop->merchant->owner)],
            'payments' => (new PaymentResource($record))->resolve($request) + ['transaction_reference' => $record->order->reference, 'transaction_kind' => $record->order->kind],
            'receipts' => (new ReceiptResource($record))->resolve($request),
            'activity' => ['action' => $record->action, 'subject_type' => $record->subject_type, 'subject_id' => (string) $record->subject_id, 'reason' => $record->reason, 'metadata' => $record->metadata, 'admin' => ['id' => (string) $record->admin_id, 'name' => $record->admin->name]],
        };
    }

    private function user(User $u): array
    {
        return ['id' => (string) $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'country_code' => $u->country_code, 'country' => $u->country?->name, 'city' => $u->city?->name,
            'role' => $u->role->value, 'status' => $u->disabled_at ? 'suspended' : 'active',
            'avatar_url' => $u->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
            'created_at' => $u->created_at?->toISOString(), 'email_verified_at' => $u->email_verified_at?->toISOString()];
    }

    public function detail(string $type, $record, Request $request): array
    {
        $data = $this->dto($type, $record, $request);
        if ($type === 'users') {
            $data['counts'] = ['favorites' => Favorite::where('user_id', $record->id)->count(),
                'reservations' => Reservation::where('user_id', $record->id)->count(), 'orders' => Order::where('user_id', $record->id)->where('kind', 'sale')->count()];
        }
        $filters = match ($type) {
            'users' => ['user_id' => $record->id], 'merchants' => ['merchant_id' => $record->id],
            'shops' => ['shop_id' => $record->id], 'vehicles' => ['vehicle_id' => $record->id], default => [],
        };
        if ($filters) {
            $related = match ($type) {
                'users' => ['reservations', 'orders', 'receipts'],
                'merchants' => ['shops', 'vehicles', 'reservations', 'orders'],
                'shops', 'vehicles' => ['reservations', 'orders'],
            };
            if ($type === 'shops') {
                $related[] = 'vehicles';
            }
            foreach ($related as $target) {
                $q = $this->filtered($target, $filters);
                $data['related'][$target] = ['count' => (clone $q)->count(), 'filters' => $filters,
                    'items' => $q->orderByDesc('id')->limit(5)->get()->map(fn ($r) => $this->dto($target, $r, $request))->all()];
            }
            $data['activity'] = AdminActivityLog::where('subject_type', $type)->where('subject_id', $record->id)->with('admin.country', 'admin.city')->orderByDesc('id')->limit(10)->get()->map(fn ($r) => $this->dto('activity', $r, $request))->all();
            if (in_array($type, ['merchants', 'shops'])) {
                $orders = Order::where('is_demo', true)->whereHas('payment', fn ($q) => $q->where('is_demo', true)->where('status', 'paid'));
                $type === 'shops' ? $orders->where('shop_id', $record->id) : $orders->whereHas('shop', fn ($q) => $q->where('merchant_id', $record->id));
                $data['demo_revenue'] = $orders->selectRaw('currency_code AS currency, SUM(total_minor) AS amount_minor')->groupBy('currency_code')->get()->map(fn ($r) => ['currency' => $r->currency, 'amount_minor' => (string) $r->amount_minor])->all();
            }
        }

        return $data;
    }

    public function dashboard(array $p, Request $request): array
    {
        return DB::transaction(function () use ($p, $request) {
            $asOf = CarbonImmutable::now('UTC');
            $days = (int) ($p['period'] ?? 30);
            $start = $asOf->startOfDay()->subDays($days - 1);
            $scope = $p['scope'] ?? 'demo';
            $user = User::query();
            $merchant = MerchantProfile::query();
            $shop = Shop::query();
            $vehicle = Vehicle::query();
            $reservation = Reservation::query();
            $order = Order::where('kind', 'sale');
            foreach ([$shop, $vehicle, $reservation, $order] as $q) {
                if ($scope !== 'all') {
                    $q->where('is_demo', $scope === 'demo');
                }
            }
            if ($scope !== 'all') {
                $user->where('email', $scope === 'demo' ? 'like' : 'not like', '%@bolidemarket.demo');
                $merchant->whereHas('owner', fn ($q) => $q->where('email', $scope === 'demo' ? 'like' : 'not like', '%@bolidemarket.demo'));
            }
            $fleet = (clone $vehicle)->selectRaw('inventory_status, COUNT(*) AS total')->groupBy('inventory_status')->pluck('total', 'inventory_status');
            $roles = (clone $user)->selectRaw('role, COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');
            $counts = ['clients' => (int) ($roles['customer'] ?? 0), 'merchants' => (clone $merchant)->count(), 'shops' => (clone $shop)->count(),
                'vehicles_active' => (clone $vehicle)->publiclyVisible()->where('inventory_status', '!=', 'sold')->count(), 'vehicles_sold' => (int) ($fleet['sold'] ?? 0),
                'rentals_active' => (clone $reservation)->where('status', 'active')->count(),
                'reservations_pending' => (clone $reservation)->where('status', 'pending')->where('expires_at', '>', $asOf)->count(),
                'transactions_demo' => Payment::where('is_demo', true)->whereBetween('created_at', [$start, $asOf])->count()];
            $series = [];
            foreach (['registrations' => $user, 'listings' => $vehicle, 'reservations' => $reservation, 'sales' => (clone $order)->where('status', 'fulfilled')] as $key => $q) {
                $date = $key === 'sales' ? 'fulfilled_at' : 'created_at';
                $monthly = $days > 30;
                $format = $monthly ? '%Y-%m' : '%Y-%m-%d';
                $rows = (clone $q)->whereBetween($date, [$start, $asOf])->selectRaw("DATE_FORMAT($date, '$format') AS bucket, COUNT(*) AS total")->groupBy('bucket')->pluck('total', 'bucket');
                $series[$key] = [];
                for ($d = $monthly ? $start->startOfMonth() : $start; $d->lte($asOf); $d = $monthly ? $d->addMonth() : $d->addDay()) {
                    $bucket = $d->format($monthly ? 'Y-m' : 'Y-m-d');
                    $series[$key][] = ['date' => $bucket, 'count' => (int) ($rows[$bucket] ?? 0)];
                }
            }
            $geo = (clone $vehicle)->selectRaw('country_code, city_id, COUNT(*) AS total')->with('country', 'city')->groupBy('country_code', 'city_id')->get()->map(fn ($r) => ['country_code' => $r->country_code, 'country' => $r->country?->name, 'city' => $r->city?->name, 'count' => (int) $r->total]);
            $payments = Payment::where('is_demo', true)->where('status', 'paid')->whereBetween('paid_at', [$start, $asOf])->selectRaw('currency_code AS currency, minor_unit, SUM(amount_minor) AS amount_minor, COUNT(*) AS count')->groupBy('currency_code', 'minor_unit')->get()->map(fn ($r) => ['currency' => $r->currency, 'minor_unit' => $r->minor_unit, 'amount_minor' => (string) $r->amount_minor, 'count' => (int) $r->count]);

            return ['as_of' => $asOf->toISOString(), 'period' => $days, 'scope' => $scope, 'starts_at' => $start->toISOString(),
                'kpis' => $counts, 'fleet' => ['total' => array_sum($fleet->all()), 'available' => (int) ($fleet['available'] ?? 0), 'rented' => (int) ($fleet['rented'] ?? 0), 'sold' => (int) ($fleet['sold'] ?? 0), 'other' => (int) ($fleet['other'] ?? 0)],
                'series' => $series, 'geography' => $geo, 'payments_demo' => $payments,
                'activity' => AdminActivityLog::with('admin.country', 'admin.city')->orderByDesc('id')->limit(10)->get()->map(fn ($r) => $this->dto('activity', $r, $request)),
                'demo_notice' => 'Encaissements DEMO séparés, sans conversion. Les KPI décrivent le parc actuel ; les séries et encaissements utilisent la période UTC.'];
        });
    }
}
