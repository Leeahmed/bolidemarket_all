<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Feature;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([LocationSeeder::class, CatalogReferenceSeeder::class]);
        [$this->merchant, $this->shop] = $this->makeShop();
    }

    private function makeShop(): array
    {
        $user = User::factory()->create();
        $profile = app(CreateMerchantProfile::class)->handle($user, 'Société test', 'Enseigne test');
        $profile->approval_status = MerchantApproval::APPROVED;
        $profile->save();
        $shop = new Shop;
        $shop->forceFill(['merchant_id' => $profile->id, 'name' => 'Boutique test', 'slug' => 'boutique-'.Str::ulid(),
            'address' => 'Adresse test', 'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->value('id'),
            'district_id' => District::where('slug', 'cocody')->value('id'), 'currency_code' => 'XOF',
            'timezone' => 'Africa/Abidjan', 'status' => 'published'])->save();

        return [$user->fresh(), $shop];
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['shop_id' => $this->shop->id, 'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->value('id'),
            'category_id' => Category::where('slug', 'suv')->value('id'), 'year' => 2024,
            'condition' => 'used', 'is_for_sale' => true, 'is_for_rent' => false, 'sale_price_minor' => '18500000',
            'currency_code' => 'XOF', 'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Véhicule de test'], $overrides);
    }

    public function test_vehicle_currency_is_inferred_and_wrong_currency_or_country_is_rejected(): void
    {
        Sanctum::actingAs($this->merchant);
        $payload = $this->payload();
        unset($payload['currency_code']);
        $this->postJson('/api/v1/merchant/vehicles', $payload)->assertCreated();
        $this->assertSame('XOF', Vehicle::sole()->currency_code);
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['currency_code' => 'EUR']))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['country_code' => 'FR', 'city_id' => City::where('slug', 'paris')->value('id'), 'district_id' => null]))->assertUnprocessable();
    }

    public function test_shop_country_cannot_reinterpret_existing_vehicle_amounts(): void
    {
        Sanctum::actingAs($this->merchant);
        $this->vehicle();
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['country_code' => 'FR', 'city_id' => City::where('slug', 'paris')->value('id'), 'district_id' => null])->assertUnprocessable();
        $this->assertSame('CI', $this->shop->fresh()->country_code);
    }

    private function png(): string
    {
        $chunk = fn ($type, $bytes) => pack('N', strlen($bytes)).$type.$bytes.hash('crc32b', $type.$bytes, true);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            .$chunk('tEXt', "Comment\0PRIVATE-METADATA").$chunk('IDAT', gzcompress("\0\x20\x40\x60")).$chunk('IEND', '');
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('car.png', $this->png());
    }

    private function vehicle(array $overrides = []): Vehicle
    {
        $vehicle = new Vehicle;
        $vehicle->forceFill($this->payload() + ['reference' => 'BM-TEST-'.Str::ulid(), 'slug' => 'vehicle-'.Str::ulid(),
            'title' => 'Toyota RAV4', 'country_code' => 'CI', 'city_id' => $this->shop->city_id,
            'district_id' => $this->shop->district_id, 'publication_status' => 'published', 'published_at' => now()]);
        $vehicle->forceFill($overrides)->save();
        $key = 'vehicles/'.$vehicle->id.'/test.png';
        Storage::disk('public')->put($key, $this->png());
        $vehicle->images()->create(['storage_key' => $key, 'position' => 0]);

        return $vehicle->refresh();
    }

    private function login(?User $user = null): void
    {
        Sanctum::actingAs($user ?? $this->merchant);
    }

    public function test_public_list_is_paginated_and_money_is_exact(): void
    {
        $this->vehicle();
        $this->vehicle();
        $this->getJson('/api/v1/vehicles?per_page=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.sale_price.amount_minor', '18500000')
            ->assertJsonPath('data.0.sale_price.currency', 'XOF')->assertJsonPath('data.0.sale_price.minor_unit', 0);
        $this->getJson('/api/v1/vehicles?per_page=101')->assertUnprocessable();
    }

    public function test_unpublished_archived_and_future_publication_are_not_public(): void
    {
        foreach ([['publication_status' => 'draft'], ['publication_status' => 'archived'], ['published_at' => now()->addDay()]] as $state) {
            $v = $this->vehicle($state);
            $this->getJson('/api/v1/vehicles/'.$v->slug)->assertNotFound();
        }
        $this->getJson('/api/v1/vehicles')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_vehicle_detail_contains_relations_but_no_private_account_or_vin(): void
    {
        $v = $this->vehicle(['vin' => '1HGBH41JXMN109186', 'license_plate' => 'PRIVATE']);
        $v->features()->sync([Feature::first()->id]);
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertOk()->assertJsonPath('data.brand.name', 'Toyota')
            ->assertJsonCount(1, 'data.images')->assertJsonPath('data.primary_image.is_primary', true)
            ->assertJsonCount(1, 'data.features')->assertJsonPath('data.location.city.name', 'Abidjan')
            ->assertJsonMissingPath('data.vin')->assertJsonMissingPath('data.license_plate')
            ->assertJsonMissingPath('data.shop.merchant.email')->assertJsonMissingPath('data.shop.merchant.owner');
    }

    public function test_shops_list_and_detail_count_only_published_vehicles(): void
    {
        $this->vehicle();
        $this->vehicle(['publication_status' => 'draft']);
        $this->getJson('/api/v1/shops')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.published_vehicles_count', 1);
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertOk()->assertJsonCount(1, 'data.vehicles')
            ->assertJsonPath('data.published_vehicles_count', 1);
        $this->getJson('/api/v1/shops/'.$this->shop->slug.'/vehicles')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_pending_merchant_hides_shop_and_vehicle(): void
    {
        $v = $this->vehicle();
        $this->shop->merchant->approval_status = MerchantApproval::PENDING;
        $this->shop->merchant->save();
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertNotFound();
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertNotFound();
    }

    public function test_inactive_location_hides_public_records(): void
    {
        $v = $this->vehicle();
        Country::whereKey('CI')->update(['active' => false]);
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertNotFound();
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertNotFound();
    }

    public function test_merchant_creates_vehicle_with_stable_unique_reference_and_slug(): void
    {
        $this->login();
        $first = $this->postJson('/api/v1/merchant/vehicles', $this->payload())->assertCreated()
            ->assertJsonPath('data.publication_status', 'draft')->assertJsonPath('data.location.country.code', 'CI');
        $second = $this->postJson('/api/v1/merchant/vehicles', $this->payload())->assertCreated();
        $this->assertNotSame($first->json('data.reference'), $second->json('data.reference'));
        $this->assertNotSame($first->json('data.slug'), $second->json('data.slug'));
        $updated = $this->putJson('/api/v1/merchant/vehicles/'.$first->json('data.id'), ['title' => 'Nouveau titre'])
            ->assertOk()->assertJsonPath('data.title', 'Nouveau titre');
        $this->assertSame($first->json('data.reference'), $updated->json('data.reference'));
        $this->assertSame($first->json('data.slug'), $updated->json('data.slug'));
    }

    public function test_client_and_anonymous_cannot_manage_vehicles_or_shops(): void
    {
        $this->postJson('/api/v1/merchant/vehicles', $this->payload())->assertUnauthorized();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/merchant/vehicles', $this->payload())->assertForbidden();
        $this->postJson('/api/v1/merchant/shops', [])->assertForbidden();
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, [])->assertForbidden();
    }

    public function test_cross_merchant_access_is_denied_for_every_vehicle_mutation(): void
    {
        $v = $this->vehicle();
        $image = $v->images()->first();
        [$other] = $this->makeShop();
        $this->login($other);
        $base = '/api/v1/merchant/vehicles/'.$v->id;
        $this->getJson($base)->assertForbidden();
        $this->putJson($base, ['title' => 'Intrusion'])->assertForbidden();
        $this->patchJson($base.'/status', ['publication_status' => 'draft'])->assertForbidden();
        $this->deleteJson($base)->assertForbidden();
        $this->post($base.'/images', ['image' => $this->upload()], ['Accept' => 'application/json'])->assertForbidden();
        $this->patchJson($base.'/images/'.$image->id.'/primary')->assertForbidden();
        $this->deleteJson($base.'/images/'.$image->id)->assertForbidden();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload())->assertForbidden();
        $this->assertDatabaseHas('vehicles', ['id' => $v->id, 'title' => 'Toyota RAV4', 'deleted_at' => null]);
    }

    public function test_merchant_list_excludes_other_merchants(): void
    {
        $mine = $this->vehicle();
        [, $otherShop] = $this->makeShop();
        $this->vehicle(['shop_id' => $otherShop->id]);
        $this->login();
        $this->getJson('/api/v1/merchant/vehicles')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $mine->id);
    }

    public function test_sale_and_rental_prices_are_required_and_fractional_prices_rejected(): void
    {
        $this->login();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['sale_price_minor' => null]))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['is_for_sale' => false, 'is_for_rent' => true]))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['sale_price_minor' => '18.50']))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['is_for_sale' => false, 'is_for_rent' => false]))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['is_for_rent' => true, 'rent_daily_minor' => '45000']))
            ->assertCreated()->assertJsonPath('data.offer_types', ['sale', 'rent'])->assertJsonPath('data.rental_daily_price.unit', 'day');
    }

    public function test_update_cannot_remove_active_sale_price(): void
    {
        $v = $this->vehicle();
        $this->login();
        $this->putJson('/api/v1/merchant/vehicles/'.$v->id, ['sale_price_minor' => null])->assertUnprocessable();
        $this->assertSame('18500000', $v->fresh()->sale_price_minor);
    }

    public function test_location_hierarchy_coordinates_and_features_are_validated(): void
    {
        $this->login();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['city_id' => City::where('slug', 'paris')->value('id')]))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['latitude' => 5]))->assertUnprocessable();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['feature_ids' => [999999]]))->assertUnprocessable();
    }

    public function test_server_managed_flags_and_slug_cannot_be_forged(): void
    {
        $this->login();
        $this->postJson('/api/v1/merchant/vehicles', $this->payload(['is_certified' => true, 'slug' => 'fake', 'publication_status' => 'published']))->assertUnprocessable();
    }

    public function test_publication_and_inventory_are_independent(): void
    {
        $v = $this->vehicle(['publication_status' => 'draft', 'published_at' => null]);
        $this->login();
        $base = '/api/v1/merchant/vehicles/'.$v->id.'/status';
        $this->patchJson($base, ['publication_status' => 'published'])->assertOk();
        $this->patchJson($base, ['inventory_status' => 'other'])->assertOk()->assertJsonPath('data.is_available_now', false);
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertOk()->assertJsonPath('data.publication_status', 'published');
        $this->patchJson($base, ['inventory_status' => 'sold'])->assertUnprocessable();
        $this->patchJson($base, ['inventory_status' => 'rented'])->assertUnprocessable();
        $this->patchJson($base, ['publication_status' => 'draft'])->assertOk();
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertNotFound();
    }

    public function test_publication_requires_cover_and_approved_merchant(): void
    {
        $this->login();
        $id = $this->postJson('/api/v1/merchant/vehicles', $this->payload())->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/merchant/vehicles/'.$id.'/status', ['publication_status' => 'published'])->assertUnprocessable();
        $v = $this->vehicle(['publication_status' => 'draft']);
        $this->shop->merchant->approval_status = MerchantApproval::PENDING;
        $this->shop->merchant->save();
        $this->patchJson('/api/v1/merchant/vehicles/'.$v->id.'/status', ['publication_status' => 'published'])->assertUnprocessable();
    }

    public function test_soft_delete_preserves_vehicle_and_images_but_removes_public_access(): void
    {
        $v = $this->vehicle();
        $this->login();
        $this->deleteJson('/api/v1/merchant/vehicles/'.$v->id)->assertNoContent();
        $this->assertSoftDeleted('vehicles', ['id' => $v->id]);
        $this->assertDatabaseHas('vehicle_images', ['vehicle_id' => $v->id]);
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertNotFound();
    }

    public function test_image_upload_strips_metadata_and_assigns_exactly_one_primary(): void
    {
        $this->login();
        $id = $this->postJson('/api/v1/merchant/vehicles', $this->payload())->json('data.id');
        $base = '/api/v1/merchant/vehicles/'.$id.'/images';
        $first = $this->post($base, ['image' => $this->upload()], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.is_primary', true);
        $second = $this->post($base, ['image' => $this->upload()], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.is_primary', false);
        $vehicle = Vehicle::findOrFail($id);
        $stored = Storage::disk('public')->get($vehicle->images()->first()->storage_key);
        $this->assertStringNotContainsString('PRIVATE-METADATA', $stored);
        $this->assertNotFalse(getimagesizefromstring($stored));
        $this->patchJson($base.'/'.$second->json('data.id').'/primary')->assertOk()->assertJsonPath('data.is_primary', true);
        $this->assertSame(1, $vehicle->images()->where('position', 0)->count());
        $this->assertSame((int) $second->json('data.id'), $vehicle->images()->first()->id);
        $this->deleteJson($base.'/'.$second->json('data.id'))->assertNoContent();
        $this->assertSame((int) $first->json('data.id'), $vehicle->images()->where('position', 0)->sole()->id);
    }

    public function test_jpeg_upload_removes_app_metadata(): void
    {
        $v = $this->vehicle();
        $this->login();
        $jpeg = file_get_contents(base_path('tests/Fixtures/vehicle.jpg'));
        $metadata = 'Exif'."\0\0".'PRIVATE-GPS';
        $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($metadata) + 2).$metadata.substr($jpeg, 2);
        $r = $this->post('/api/v1/merchant/vehicles/'.$v->id.'/images', ['image' => UploadedFile::fake()->createWithContent('photo.jpg', $jpeg)], ['Accept' => 'application/json'])->assertCreated();
        $stored = Storage::disk('public')->get($v->images()->findOrFail($r->json('data.id'))->storage_key);
        $this->assertStringNotContainsString('PRIVATE-GPS', $stored);
        $this->assertNotFalse(getimagesizefromstring($stored));
    }

    public function test_svg_upload_and_wrong_vehicle_image_are_rejected(): void
    {
        $v = $this->vehicle();
        $other = $this->vehicle();
        $this->login();
        $base = '/api/v1/merchant/vehicles/'.$v->id.'/images';
        $this->post($base, ['image' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->patchJson($base.'/'.$other->images()->first()->id.'/primary')->assertNotFound();
        $this->deleteJson($base.'/'.$other->images()->first()->id)->assertNotFound();
    }

    public function test_last_published_image_cannot_be_deleted(): void
    {
        $v = $this->vehicle();
        $this->login();
        $this->deleteJson('/api/v1/merchant/vehicles/'.$v->id.'/images/'.$v->images()->first()->id)->assertUnprocessable();
    }

    public function test_merchant_shop_creation_update_and_soft_delete(): void
    {
        $this->login();
        $data = ['merchant_id' => $this->shop->merchant_id, 'name' => 'Nouvelle boutique', 'address' => 'Adresse démo',
            'country_code' => 'CI', 'city_id' => $this->shop->city_id, 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan', 'status' => 'published'];
        $id = $this->postJson('/api/v1/merchant/shops', $data)->assertCreated()->json('data.id');
        $this->putJson('/api/v1/merchant/shops/'.$id, ['name' => 'Nouveau nom'])->assertOk()->assertJsonPath('data.name', 'Nouveau nom');
        $this->deleteJson('/api/v1/merchant/shops/'.$id)->assertNoContent();
        $this->assertSoftDeleted('shops', ['id' => $id]);
    }

    public function test_merchant_cannot_manage_another_shop(): void
    {
        [$other] = $this->makeShop();
        $this->login($other);
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['name' => 'Intrusion'])->assertForbidden();
        $this->deleteJson('/api/v1/merchant/shops/'.$this->shop->id)->assertForbidden();
    }

    public function test_deleted_shop_hides_its_vehicles(): void
    {
        $v = $this->vehicle();
        $this->shop->delete();
        $this->getJson('/api/v1/vehicles/'.$v->slug)->assertNotFound();
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertNotFound();
    }

    public function test_reference_endpoints_return_requested_small_dataset(): void
    {
        $this->getJson('/api/v1/countries')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/v1/cities?country_code=CI')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/districts?city_id='.$this->shop->city_id)->assertOk()->assertJsonCount(9, 'data');
        $this->getJson('/api/v1/features')->assertOk()->assertJsonCount(8, 'data');
    }

    public function test_vehicle_list_query_count_does_not_grow_per_vehicle(): void
    {
        $this->vehicle();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/vehicles')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 4; $i++) {
            $this->vehicle();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/vehicles')->assertOk();
        $five = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($one, $five);
    }

    public function test_marketplace_seed_is_idempotent_and_marks_demo_records(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);
        $this->seed(MarketplaceDemoSeeder::class);
        $this->assertSame(18, Vehicle::where('is_demo', true)->count());
        $this->assertSame(5, Shop::where('is_demo', true)->count());
        $this->assertSame(17, Vehicle::publiclyVisible()->where('is_demo', true)->count());
        $this->assertSame(18, DB::table('vehicle_images')->where('is_placeholder', true)->count());
    }

    public function test_marketplace_seed_refuses_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        app(MarketplaceDemoSeeder::class)->run();
    }

    public function test_transliterated_slugs_remain_within_database_limit(): void
    {
        $this->login();
        $vehicle = $this->postJson('/api/v1/merchant/vehicles', $this->payload(['title' => str_repeat('œ', 180)]))->assertCreated();
        $this->assertLessThanOrEqual(255, strlen($vehicle->json('data.slug')));
        $shop = $this->postJson('/api/v1/merchant/shops', [
            'merchant_id' => $this->shop->merchant_id, 'name' => str_repeat('œ', 150), 'address' => 'Test',
            'country_code' => 'CI', 'city_id' => $this->shop->city_id, 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan',
        ])->assertCreated();
        $this->assertLessThanOrEqual(255, strlen($shop->json('data.slug')));
    }
}
