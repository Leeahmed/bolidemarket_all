<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Shop;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeographicDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dataset géographique démo interdit hors local/testing.');
        }
        $this->call(MarketplaceDemoSeeder::class);
        DB::transaction(function () {
            // Approximate, deliberately fictional display positions; not verified addresses.
            $districts = ['cocody' => [5.3599, -4.0083], 'marcory' => [5.3000, -3.9900],
                'plateau' => [5.3250, -4.0200], 'bingerville' => [5.3550, -3.8850], 'yopougon' => [5.3400, -4.0800]];
            foreach (Vehicle::where('is_demo', true)->where('reference', 'like', 'BM-CI-DEMO-%')->whereNull('latitude')->orderBy('id')->get() as $index => $vehicle) {
                $slug = array_keys($districts)[$index % count($districts)];
                [$lat, $lng] = $districts[$slug];
                $district = District::where('slug', $slug)->where('city_id', $vehicle->city_id)->first();
                if ($district) {
                    $vehicle->forceFill(['district_id' => $district->id, 'latitude' => $lat + ($index % 3) * 0.005,
                        'longitude' => $lng, 'description' => $vehicle->description.' Position de démonstration indicative.'])->save();
                }
            }
            Vehicle::where('is_demo', true)->where('reference', 'like', 'BM-FR-DEMO-%')->whereNull('latitude')
                ->update(['latitude' => 48.8566, 'longitude' => 2.3522]);
            $template = Vehicle::with('images')->where('reference', 'BM-FR-DEMO-005')->where('is_demo', true)->firstOrFail();
            foreach ([['lyon', 'FR', 'EUR', 'Europe/Paris', 45.7640, 4.8357, 2490000],
                ['dakar', 'SN', 'XOF', 'Africa/Dakar', 14.7167, -17.4677, 16000000],
                ['bruxelles', 'BE', 'EUR', 'Europe/Brussels', 50.8503, 4.3517, 2590000]] as [$slug, $country, $currency, $timezone, $lat, $lng, $price]) {
                $city = City::where('slug', $slug)->where('country_code', $country)->sole();
                $shopSlug = $slug.'-mobilite-geo-demo';
                $shop = Shop::withTrashed()->where('slug', $shopSlug)->first();
                if (! $shop) {
                    $shop = new Shop;
                    $shop->forceFill(['merchant_id' => $template->shop->merchant_id, 'name' => $city->name.' Mobilité Démo',
                        'slug' => $shopSlug, 'description' => 'Boutique fictive. Position indicative de démonstration.',
                        'address' => 'Adresse de démonstration', 'country_code' => $country, 'city_id' => $city->id,
                        'currency_code' => $currency, 'timezone' => $timezone, 'latitude' => $lat, 'longitude' => $lng,
                        'status' => 'published', 'is_demo' => true])->save();
                }
                if (! $shop->is_demo || $shop->trashed()) {
                    continue;
                }
                $reference = 'BM-'.$country.'-GEO-'.strtoupper($slug);
                if (Vehicle::withTrashed()->where('reference', $reference)->exists()) {
                    continue;
                }
                $vehicle = $template->replicate();
                $vehicle->unsetRelations();
                $vehicle->forceFill(['shop_id' => $shop->id, 'reference' => $reference, 'slug' => strtolower($reference),
                    'country_code' => $country, 'city_id' => $city->id, 'district_id' => null,
                    'currency_code' => $currency, 'sale_price_minor' => $price, 'latitude' => $lat, 'longitude' => $lng,
                    'description' => 'Véhicule fictif, prix et position indicative de démonstration.'])->save();
                $key = 'vehicles/'.$vehicle->id.'/demo-placeholder.svg';
                Storage::disk('public')->copy($template->images->first()->storage_key, $key);
                $vehicle->images()->create(['storage_key' => $key, 'position' => 0, 'is_placeholder' => true,
                    'alt_text' => 'Placeholder de démonstration, aucune photographie disponible.']);
            }
        });
    }
}
