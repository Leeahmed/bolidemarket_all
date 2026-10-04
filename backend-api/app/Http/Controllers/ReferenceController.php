<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\ReferenceRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\District;
use App\Models\Feature;
use App\Models\VehicleModel;

class ReferenceController extends Controller
{
    public function index(ReferenceRequest $request)
    {
        $type = $request->route()->defaults['referenceType'];
        $models = ['countries' => Country::class, 'currencies' => Currency::class, 'cities' => City::class,
            'districts' => District::class, 'brands' => Brand::class, 'models' => VehicleModel::class,
            'categories' => Category::class, 'features' => Feature::class];
        $query = $models[$type]::query();
        if (in_array($type, ['countries', 'currencies', 'cities', 'districts'], true)) {
            $query->where('active', true);
        }
        if ($type === 'cities') {
            $query->whereHas('country', fn ($q) => $q->where('active', true));
            if ($request->filled('country_code')) {
                $query->where('country_code', $request->input('country_code'));
            }
        }
        if ($type === 'districts') {
            $query->whereHas('city', fn ($q) => $q->where('active', true)->whereHas('country', fn ($c) => $c->where('active', true)));
            if ($request->filled('city_id')) {
                $query->where('city_id', $request->input('city_id'));
            }
        }
        if ($type === 'models') {
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->input('brand_id'));
            }
        }

        return ReferenceResource::collection($query->get());
    }
}
