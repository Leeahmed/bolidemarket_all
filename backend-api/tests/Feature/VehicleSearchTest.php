<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\GeographicDemoSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleSearchTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocationSeeder::class, CatalogReferenceSeeder::class]);
        $profile = app(CreateMerchantProfile::class)->handle(User::factory()->create(), 'Test', 'Test');
        $profile->approval_status = MerchantApproval::APPROVED;
        $profile->save();
        $this->shop = new Shop;
        $this->shop->forceFill(['merchant_id' => $profile->id, 'name' => 'Prestige Test', 'slug' => 'prestige-test',
            'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->value('id'),
            'district_id' => District::where('slug', 'cocody')->value('id'), 'currency_code' => 'XOF',
            'timezone' => 'Africa/Abidjan', 'address' => 'Adresse de test', 'status' => 'published'])->save();
    }

    private function vehicle(array $attributes = []): Vehicle
    {
        $vehicle = new Vehicle;
        $vehicle->forceFill(array_replace(['shop_id' => $this->shop->id, 'reference' => 'TEST-'.Str::ulid(),
            'slug' => 'test-'.Str::ulid(), 'title' => 'Toyota RAV4', 'trim' => 'Adventure',
            'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->value('id'),
            'category_id' => Category::where('slug', 'suv')->value('id'), 'year' => 2024, 'condition' => 'used',
            'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Confort panoramique',
            'is_for_sale' => true, 'is_for_rent' => false, 'sale_price_minor' => 18500000, 'currency_code' => 'XOF',
            'mileage_km' => 20000, 'country_code' => 'CI', 'city_id' => $this->shop->city_id,
            'district_id' => $this->shop->district_id, 'publication_status' => 'published', 'published_at' => now(),
            'inventory_status' => 'available', 'is_featured' => false, 'is_certified' => false], $attributes))->save();

        return $vehicle;
    }

    private function search(array $params = [], string $path = '/api/v1/vehicles')
    {
        return $this->getJson($path.'?'.http_build_query($params));
    }

    public static function textCases(): array
    {
        return array_map(fn ($text) => [$text], ['Toyota', 'Toyota RAV4', 'RAV4', 'Cocody', 'Abidjan',
            'Prestige', 'Adventure', 'panoramique', 'Toyota Cocody']);
    }

    #[DataProvider('textCases')]
    public function test_text_search_covers_public_fields_and_multiple_words(string $text): void
    {
        $vehicle = $this->vehicle();
        $this->search(['q' => $text])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $vehicle->id);
        $this->search(['q' => $text.' inexistant'])->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_mercedes_abidjan_and_literal_sql_metacharacters(): void
    {
        $this->vehicle(['title' => 'Mercedes C300', 'vehicle_model_id' => VehicleModel::where('name', 'C300')->value('id')]);
        $this->search(['q' => 'Mercedes Abidjan'])->assertOk()->assertJsonPath('meta.total', 1);
        foreach (['%', '_', "' OR 1=1 --", '!'] as $term) {
            $this->search(['q' => $term])->assertOk()->assertJsonPath('meta.total', 0);
        }
    }

    public static function filterCases(): array
    {
        return [
            [['listing_type' => 'sale'], ['is_for_sale' => false, 'is_for_rent' => true, 'rent_daily_minor' => 45000]],
            [['listing_type' => 'rental'], ['is_for_rent' => false], ['is_for_rent' => true, 'rent_daily_minor' => 45000]],
            [['brand' => 'Toyota'], ['vehicle_model_id' => 'Clio']],
            [['model' => 'RAV4'], ['vehicle_model_id' => 'Corolla']],
            [['category' => 'suv'], ['category_id' => 'city']],
            [['condition' => 'used'], ['condition' => 'new']],
            [['fuel_type' => 'petrol'], ['fuel' => 'electric']],
            [['transmission' => 'automatic'], ['transmission' => 'manual']],
            [['year_min' => 2020, 'year_max' => 2025], ['year' => 2018]],
            [['is_featured' => 0], ['is_featured' => true]],
            [['is_certified' => 1], ['is_certified' => false], ['is_certified' => true]],
            [['status' => 'available'], ['inventory_status' => 'sold']],
            [['listing_type' => 'sale', 'currency' => 'XOF', 'min_price' => 18000000, 'max_price' => 19000000], ['sale_price_minor' => 17000000]],
            [['listing_type' => 'rental', 'currency' => 'XOF', 'min_price' => 40000, 'max_price' => 50000], ['is_for_rent' => true, 'rent_daily_minor' => 90000], ['is_for_rent' => true, 'rent_daily_minor' => 45000]],
        ];
    }

    #[DataProvider('filterCases')]
    public function test_individual_filters(array $filters, array $excluded, array $included = []): void
    {
        if (isset($excluded['vehicle_model_id'])) {
            $excluded['vehicle_model_id'] = VehicleModel::where('name', $excluded['vehicle_model_id'])->value('id');
        }
        if (isset($excluded['category_id'])) {
            $excluded['category_id'] = Category::where('slug', $excluded['category_id'])->value('id');
        }
        $match = $this->vehicle($included);
        $this->vehicle($excluded);
        $this->search($filters)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $match->id);
    }

    public function test_price_sort_is_exact_and_currency_and_intent_are_required(): void
    {
        $expensive = $this->vehicle(['sale_price_minor' => '99999999999998', 'is_for_rent' => true, 'rent_daily_minor' => 100]);
        $cheap = $this->vehicle(['sale_price_minor' => '99999999999997', 'is_for_rent' => true, 'rent_daily_minor' => 200]);
        $this->vehicle(['currency_code' => 'EUR', 'sale_price_minor' => 1]);
        $base = ['currency' => 'XOF', 'listing_type' => 'sale'];
        $this->search($base + ['sort' => 'price_asc'])->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', (string) $cheap->id);
        $this->search($base + ['sort' => 'price_desc'])->assertOk()->assertJsonPath('data.0.id', (string) $expensive->id);
        $this->search(['currency' => 'XOF', 'listing_type' => 'rental', 'sort' => 'price_asc'])->assertOk()->assertJsonPath('data.0.id', (string) $expensive->id);
        foreach ([['sort' => 'price_asc'], ['min_price' => 1], ['sort' => 'price_desc', 'currency' => 'EUR'], ['max_price' => 2, 'listing_type' => 'sale']] as $invalid) {
            $this->search($invalid)->assertUnprocessable();
        }
    }

    public function test_newest_year_mileage_and_pagination_are_stable(): void
    {
        $new = $this->vehicle(['year' => 2025, 'mileage_km' => 10]);
        $old = $this->vehicle(['published_at' => now()->subDay(), 'year' => 2020, 'mileage_km' => 100]);
        $this->vehicle(['published_at' => now()->subDays(2), 'mileage_km' => null]);
        foreach (['newest', 'year_desc', 'mileage_asc'] as $sort) {
            $this->search(['sort' => $sort])->assertOk()->assertJsonPath('data.0.id', (string) $new->id);
        }
        $page = $this->search(['q' => 'Toyota', 'per_page' => 1, 'page' => 2])->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', (string) $old->id);
        $this->assertStringContainsString('q=Toyota', $page->json('links.next'));
    }

    public static function invalidCases(): array
    {
        return array_map(fn ($params) => [$params], [
            ['latitude' => 91, 'longitude' => 0], ['latitude' => 0, 'longitude' => -181],
            ['latitude' => 0], ['longitude' => 0], ['latitude' => 'abc', 'longitude' => 0],
            ['latitude' => '', 'longitude' => ''], ['latitude' => [0], 'longitude' => 0],
            ['radius' => 5], ['radius' => 501, 'latitude' => 0, 'longitude' => 0],
            ['radius' => 0, 'latitude' => 0, 'longitude' => 0], ['sort' => 'distance'], ['sort' => 'popular'],
            ['year_min' => 2025, 'year_max' => 2020], ['min_price' => 20, 'max_price' => 10, 'currency' => 'XOF', 'listing_type' => 'sale'],
            ['country_id' => 'CI', 'country_code' => 'FR'], ['status' => 'draft'], ['listing_type' => 'rent'],
        ]);
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_search_is_rejected(array $params): void
    {
        $this->search($params)->assertUnprocessable();
    }

    public function test_distance_radius_null_coordinates_and_shop_fallback(): void
    {
        $unknown = $this->vehicle();
        $far = $this->vehicle(['latitude' => 0, 'longitude' => 1]);
        $near = $this->vehicle(['latitude' => 0, 'longitude' => 0.1]);
        $zero = $this->vehicle(['latitude' => 0, 'longitude' => 0]);
        $origin = ['latitude' => 0, 'longitude' => 0];
        $result = $this->search($origin + ['sort' => 'distance'])->assertOk();
        $this->assertSame(array_map('strval', [$zero->id, $near->id, $far->id, $unknown->id]), array_column($result->json('data'), 'id'));
        $this->assertEqualsWithDelta(11.1195, $result->json('data.1.distance_km'), 0.002);
        $result->assertJsonPath('data.3.distance_km', null);
        $this->search()->assertOk()->assertJsonPath('data.0.distance_km', null);
        $this->search($origin + ['radius' => 12], '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 2);
        $this->search($origin, '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 2);
        $this->search($origin + ['radius' => 11.119], '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 1);
        $this->search($origin + ['radius' => 12])->assertOk()->assertJsonPath('meta.total', 2);
        $this->search([], '/api/v1/nearby/vehicles')->assertUnprocessable();
        $this->search($origin + ['sort' => 'newest'], '/api/v1/nearby/vehicles')->assertUnprocessable();
        $this->shop->forceFill(['latitude' => 0, 'longitude' => 0.05])->save();
        $this->search($origin + ['radius' => 6], '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_geographic_priority_is_generic_for_cocody_and_paris_and_works_without_gps(): void
    {
        $paris = City::where('slug', 'paris')->sole();
        $lyon = City::where('slug', 'lyon')->sole();
        $bouake = City::where('slug', 'bouake')->sole();
        $ciOther = $this->vehicle(['city_id' => $bouake->id, 'district_id' => null, 'latitude' => 7.69, 'longitude' => -5.03]);
        $frOther = $this->vehicle(['country_code' => 'FR', 'city_id' => $lyon->id, 'district_id' => null, 'latitude' => 45.76, 'longitude' => 4.84]);
        $fr = $this->vehicle(['country_code' => 'FR', 'city_id' => $paris->id, 'district_id' => null, 'latitude' => 48.8566, 'longitude' => 2.3522]);
        $abidjan = $this->vehicle(['district_id' => District::where('slug', 'marcory')->value('id'), 'latitude' => 5.30, 'longitude' => -3.99]);
        $cocodyFar = $this->vehicle(['latitude' => 5.38, 'longitude' => -3.95]);
        $cocodyNear = $this->vehicle(['latitude' => 5.3599, 'longitude' => -4.0083]);
        $cocodyUnknown = $this->vehicle();
        $params = ['location_mode' => 'rank', 'district_id' => $this->shop->district_id, 'latitude' => 5.3599, 'longitude' => -4.0083];
        $result = $this->search($params)->assertOk();
        $this->assertSame(array_map('strval', [$cocodyNear->id, $cocodyFar->id, $cocodyUnknown->id, $abidjan->id, $ciOther->id]), array_slice(array_column($result->json('data'), 'id'), 0, 5));
        $this->search(['location_mode' => 'rank', 'country_id' => 'FR', 'city_id' => $paris->id, 'latitude' => 48.8566, 'longitude' => 2.3522])
            ->assertOk()->assertJsonPath('data.0.id', (string) $fr->id)->assertJsonPath('data.1.id', (string) $frOther->id)->assertJsonPath('meta.total', 7);
        $this->search(['location_mode' => 'rank', 'city_id' => $paris->id])->assertOk()->assertJsonPath('data.0.id', (string) $fr->id)->assertJsonPath('data.0.distance_km', null);
        $this->search(['country_id' => 'FR'])->assertOk()->assertJsonPath('meta.total', 2);
        $this->search(['city_id' => $paris->id])->assertOk()->assertJsonPath('meta.total', 1);
        $this->search(['district_id' => $this->shop->district_id])->assertOk()->assertJsonPath('meta.total', 3);
        $this->search(['country_code' => 'CI', 'city_id' => $paris->id])->assertUnprocessable();
        $this->search(['city_id' => $paris->id, 'district_id' => $this->shop->district_id])->assertUnprocessable();
    }

    public function test_all_search_surfaces_hide_private_vehicles_and_suggestions_are_bounded(): void
    {
        $hidden = $this->vehicle(['title' => 'Private', 'publication_status' => 'draft', 'vehicle_model_id' => VehicleModel::where('name', '911')->value('id'), 'category_id' => Category::where('slug', 'sport')->value('id')]);
        $this->search(['q' => 'Porsche'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->search(['q' => 'Porsche'], '/api/v1/search/suggestions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/vehicles/filters')->assertOk()->assertJsonCount(0, 'data.brands')->assertJsonCount(0, 'data.categories');
        foreach (VehicleModel::with('brand')->limit(12)->get() as $model) {
            $this->vehicle(['vehicle_model_id' => $model->id, 'title' => $model->brand->name.' '.$model->name, 'latitude' => 0, 'longitude' => 0]);
        }
        $this->search(['q' => 'Confort'], '/api/v1/search/suggestions')->assertOk()->assertJsonCount(8, 'data');
        $this->getJson('/api/v1/vehicles/filters')->assertOk()->assertJsonPath('data.categories.0.slug', 'suv');
        foreach ([['publication_status' => 'archived'], ['publication_status' => 'published', 'published_at' => now()->addDay()]] as $state) {
            $hidden->forceFill($state)->save();
            $this->search(['q' => 'Porsche'])->assertOk()->assertJsonPath('meta.total', 0);
        }
        $this->shop->status = 'suspended';
        $this->shop->save();
        $this->search(['latitude' => 0, 'longitude' => 0], '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 0);
        $this->search(['q' => 'Toyota'], '/api/v1/search/suggestions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_eager_loading_query_count_does_not_grow_per_result(): void
    {
        $this->vehicle(['latitude' => 0, 'longitude' => 0]);
        DB::enableQueryLog();
        $this->search(['latitude' => 0, 'longitude' => 0])->assertOk();
        $one = count(DB::getQueryLog());
        for ($i = 0; $i < 4; $i++) {
            $this->vehicle(['latitude' => 0, 'longitude' => $i]);
        }
        DB::flushQueryLog();
        $this->search(['latitude' => 0, 'longitude' => 0])->assertOk()->assertJsonCount(5, 'data');
        $this->assertSame($one, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_nearby_excludes_unpublished_deleted_and_unapproved_even_at_origin(): void
    {
        foreach ([['publication_status' => 'draft'], ['publication_status' => 'archived'],
            ['published_at' => now()->addDay()], ['deleted_at' => now()]] as $state) {
            $this->vehicle($state + ['latitude' => 0, 'longitude' => 0]);
        }
        $this->search(['latitude' => 0, 'longitude' => 0], '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 0);
        $this->vehicle(['latitude' => 0, 'longitude' => 0]);
        $this->shop->merchant->forceFill(['approval_status' => MerchantApproval::PENDING])->save();
        $this->search(['latitude' => 0, 'longitude' => 0], '/api/v1/nearby/vehicles')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_distance_handles_antimeridian_and_poles(): void
    {
        $this->vehicle(['latitude' => 0, 'longitude' => -179.9]);
        $this->search(['latitude' => 0, 'longitude' => 179.9, 'radius' => 25], '/api/v1/nearby/vehicles')
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->vehicle(['latitude' => 90, 'longitude' => 180]);
        $this->search(['latitude' => 90, 'longitude' => -180, 'radius' => 1], '/api/v1/nearby/vehicles')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.distance_km', 0);
    }

    public function test_geographic_demo_is_small_idempotent_and_preserves_existing_coordinates(): void
    {
        Storage::fake('public');
        $this->seed(GeographicDemoSeeder::class);
        $demo = Vehicle::where('reference', 'BM-CI-DEMO-001')->sole();
        $demo->latitude = 5.123;
        $demo->save();
        $this->seed(GeographicDemoSeeder::class);
        $this->assertSame(21, Vehicle::where('is_demo', true)->count());
        $this->assertSame(8, Shop::where('is_demo', true)->count());
        $this->assertEquals(5.123, $demo->fresh()->latitude);
        foreach (['paris', 'lyon', 'dakar', 'bruxelles'] as $city) {
            $this->assertTrue(Vehicle::publiclyVisible()->where('city_id', City::where('slug', $city)->value('id'))->exists());
        }
        foreach (['cocody', 'marcory', 'plateau', 'bingerville', 'yopougon'] as $district) {
            $this->assertTrue(Vehicle::publiclyVisible()->where('district_id', District::where('slug', $district)->value('id'))->exists());
        }
    }

    public function test_geographic_demo_cannot_run_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        app(GeographicDemoSeeder::class)->run();
    }

    public function test_shop_catalog_filters_offers_and_status_before_pagination(): void
    {
        $sale = $this->vehicle();
        $rental = $this->vehicle(['is_for_sale' => false, 'sale_price_minor' => null, 'is_for_rent' => true, 'rent_daily_minor' => 45000]);
        $this->vehicle(['inventory_status' => 'sold']);
        $this->vehicle(['publication_status' => 'draft']);
        $otherShop = $this->shop->replicate();
        $otherShop->slug = 'other-shop';
        $otherShop->save();
        $this->vehicle(['shop_id' => $otherShop->id]);
        $path = '/api/v1/shops/'.$this->shop->slug.'/vehicles';
        $this->search(['listing_type' => 'rental', 'per_page' => 1], $path)->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $rental->id);
        $this->search(['listing_type' => 'sale', 'status' => 'available'], $path)->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $sale->id);
        $this->search(['status' => 'available'], $path)->assertOk()->assertJsonPath('meta.total', 2);
        $this->search(['listing_type' => 'invalid'], $path)->assertStatus(422);
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertOk()
            ->assertJsonPath('data.sale_vehicles_count', 2)->assertJsonPath('data.rental_vehicles_count', 1);
    }

    public function test_shop_catalog_cannot_leak_another_or_unpublished_shop(): void
    {
        $this->vehicle();
        $this->shop->status = 'draft';
        $this->shop->save();
        $this->search(['listing_type' => 'sale'], '/api/v1/shops/'.$this->shop->slug.'/vehicles')->assertNotFound();
        $this->search([], '/api/v1/shops/unknown-shop/vehicles')->assertNotFound();
    }
}
