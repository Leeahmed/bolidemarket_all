<?php

namespace App\Services;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCondition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Builder;

class VehicleSearchService
{
    public function search(array $filters, bool $nearby = false, ?int $shopId = null)
    {
        $origin = LocationContext::fromFilters($filters);
        $base = $this->filtered($filters)->select('vehicles.*');
        if ($shopId !== null) {
            $base->where('vehicles.shop_id', $shopId);
        }
        if ($origin->hasCoordinates()) {
            // Use a complete coordinate pair; never mix a vehicle latitude with a shop longitude.
            $base->join('shops as distance_shop', 'distance_shop.id', '=', 'vehicles.shop_id');
            $pair = 'vehicles.latitude IS NOT NULL AND vehicles.longitude IS NOT NULL';
            $lat = "(CASE WHEN $pair THEN vehicles.latitude ELSE distance_shop.latitude END)";
            $lng = "(CASE WHEN $pair THEN vehicles.longitude ELSE distance_shop.longitude END)";
            $base->selectRaw("? * 2 * ASIN(SQRT(LEAST(1, GREATEST(0, POWER(SIN(RADIANS($lat - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS($lat)) * POWER(SIN(RADIANS($lng - ?) / 2), 2))))) AS distance_km",
                [config('search.earth_radius_km'), $origin->latitude, $origin->latitude, $origin->longitude]);
        } else {
            $base->selectRaw('NULL AS distance_km');
        }
        // Define distance once, then filter/order the derived table by its alias.
        $query = Vehicle::query()->fromSub($base->toBase(), 'vehicles')->with(Vehicle::PUBLIC_RELATIONS);
        if ($nearby || isset($filters['radius'])) {
            $query->where('distance_km', '<=', $filters['radius'] ?? config('search.default_radius_km'));
        }
        $sort = $nearby ? 'distance' : ($filters['sort'] ?? 'relevance');
        if ($sort === 'relevance' && ($filters['location_mode'] ?? 'filter') === 'rank') {
            $cases = [];
            $bindings = [];
            foreach (['district_id' => $origin->districtId, 'city_id' => $origin->cityId, 'country_code' => $origin->countryCode] as $column => $value) {
                if ($value !== null) {
                    $cases[] = "WHEN $column = ? THEN ".count($cases);
                    $bindings[] = $value;
                }
            }
            if ($cases) {
                $query->orderByRaw('CASE '.implode(' ', $cases).' ELSE 3 END', $bindings);
            }
        }
        if ($sort === 'distance' || ($sort === 'relevance' && $origin->hasCoordinates())) {
            $query->orderByRaw('distance_km IS NULL')->orderBy('distance_km');
        } elseif (in_array($sort, ['price_asc', 'price_desc'], true)) {
            $price = $filters['listing_type'] === 'sale' ? 'sale_price_minor' : 'rent_daily_minor';
            $query->orderByRaw("$price IS NULL")->orderBy($price, $sort === 'price_asc' ? 'asc' : 'desc');
        } elseif ($sort === 'year_desc') {
            $query->orderByDesc('year');
        } elseif ($sort === 'mileage_asc') {
            $query->orderByRaw('mileage_km IS NULL')->orderBy('mileage_km');
        }

        return $query->orderByDesc('published_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 20)->withQueryString();
    }

    private function filtered(array $filters): Builder
    {
        $query = Vehicle::publiclyVisible();
        if (isset($filters['q'])) {
            $this->textSearch($query, $filters['q']);
        }
        foreach (['condition' => 'condition', 'fuel_type' => 'fuel', 'transmission' => 'transmission',
            'status' => 'inventory_status', 'is_featured' => 'is_featured', 'is_certified' => 'is_certified', 'currency' => 'currency_code'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where('vehicles.'.$column, $filters[$key]);
            }
        }
        if (($filters['location_mode'] ?? 'filter') === 'filter') {
            foreach (['country_code', 'city_id', 'district_id'] as $column) {
                if (isset($filters[$column])) {
                    $query->where('vehicles.'.$column, $filters[$column]);
                }
            }
        }
        foreach (['brand' => 'vehicleModel.brand', 'model' => 'vehicleModel', 'category' => 'category'] as $key => $relation) {
            if (isset($filters[$key])) {
                $query->whereHas($relation, fn ($q) => $q->where($key === 'category' ? 'slug' : 'name', $filters[$key]));
            }
        }
        if (isset($filters['listing_type'])) {
            $query->where($filters['listing_type'] === 'sale' ? 'is_for_sale' : 'is_for_rent', true);
        }
        foreach (['year_min' => '>=', 'year_max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where('year', $operator, $filters[$key]);
            }
        }
        $price = ($filters['listing_type'] ?? null) === 'sale' ? 'sale_price_minor' : 'rent_daily_minor';
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where($price, $operator, $filters[$key]);
            }
        }

        return $query;
    }

    private function textSearch(Builder $query, string $text): void
    {
        // Each word must match at least one public field. LIKE metacharacters stay literal.
        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $query->where(function ($word) use ($pattern) {
                foreach (['title', 'trim', 'description'] as $column) {
                    $word->orWhereRaw("vehicles.`$column` LIKE ? ESCAPE '!'", [$pattern]);
                }
                foreach (['vehicleModel.brand', 'vehicleModel', 'shop', 'city', 'district'] as $relation) {
                    $word->orWhereHas($relation, fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
                }
            });
        }
    }

    public function facets(): array
    {
        $public = Vehicle::publiclyVisible();

        return [
            'brands' => Brand::whereIn('id', VehicleModel::whereIn('id', (clone $public)->select('vehicle_model_id'))->select('brand_id'))->orderBy('name')->get(['name', 'slug'])->toArray(),
            'categories' => Category::whereIn('id', (clone $public)->select('category_id'))->orderBy('label')->get(['slug', 'label'])->toArray(),
            'fuel_types' => array_column(FuelType::cases(), 'value'),
            'transmissions' => array_column(Transmission::cases(), 'value'),
            'conditions' => array_column(VehicleCondition::cases(), 'value'),
            'listing_types' => ['sale', 'rental'],
            'sorts' => ['newest', 'price_asc', 'price_desc', 'year_desc', 'mileage_asc', 'distance'],
            'unavailable_sorts' => ['popular'],
            'max_radius_km' => config('search.max_radius_km'),
        ];
    }

    public function suggestions(string $text): array
    {
        $query = Vehicle::publiclyVisible();
        $this->textSearch($query, $text);

        return $query->with('vehicleModel.brand')->orderBy('title')->orderBy('id')
            ->limit(config('search.suggestions_limit'))->get()->map(fn (Vehicle $vehicle) => [
                'type' => 'vehicle', 'label' => $vehicle->vehicleModel->brand->name.' '.$vehicle->vehicleModel->name,
                'slug' => $vehicle->slug,
            ])->unique('label')->values()->all();
    }
}
