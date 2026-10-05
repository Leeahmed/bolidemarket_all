<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Models\Category;
use App\Models\City;
use App\Models\PriceOffer;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceOfferHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $other;

    private User $merchant;

    private User $outsider;

    private Shop $shop;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        $this->seed([LocationSeeder::class, CatalogReferenceSeeder::class]);
        $this->client = User::factory()->create();
        $this->other = User::factory()->create();
        $this->merchant = User::factory()->create();
        $profile = app(CreateMerchantProfile::class)->handle($this->merchant, 'Test Merchant', 'Test Merchant');
        $profile->approval_status = MerchantApproval::APPROVED;
        $profile->save();
        $this->merchant->refresh();
        $this->outsider = User::factory()->create();
        app(CreateMerchantProfile::class)->handle($this->outsider, 'Other Merchant', 'Other Merchant');
        $this->outsider->refresh();
        $this->shop = new Shop;
        $this->shop->forceFill(['merchant_id' => $profile->id, 'name' => 'Commerce Demo', 'slug' => 'commerce-demo', 'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->value('id'), 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan', 'address' => 'Demo', 'status' => 'published', 'is_demo' => true])->save();
        $this->vehicle = new Vehicle;
        $this->vehicle->forceFill(['shop_id' => $this->shop->id, 'reference' => 'TEST-'.Str::ulid(), 'slug' => 'test-commerce', 'title' => 'Toyota RAV4', 'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->value('id'), 'category_id' => Category::where('slug', 'suv')->value('id'), 'year' => 2024, 'condition' => 'used', 'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Demo', 'is_for_sale' => true, 'is_for_rent' => true, 'sale_price_minor' => 18500000, 'rent_daily_minor' => 45000, 'currency_code' => 'XOF', 'country_code' => 'CI', 'city_id' => $this->shop->city_id, 'publication_status' => 'published', 'published_at' => now(), 'inventory_status' => 'available', 'is_demo' => true, 'version' => 1])->save();
        Http::preventStrayRequests();
        Sanctum::actingAs($this->client);
    }

    private function offer(array $extra = [], ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/price-offers', array_replace(['vehicle_id' => $this->vehicle->id, 'amount_minor' => 17000000, 'currency' => 'XOF'], $extra));
    }

    private function buy(array $extra = [], ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/orders', array_replace(['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO', 'handover' => $this->purchaseHandover()], $extra));
    }

    private function enable(): void
    {
        $this->vehicle->update(['negotiation_enabled' => true]);
    }

    private function accepted(): string
    {
        $this->enable();
        $id = $this->offer()->assertCreated()->json('data.id');
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/price-offers/'.$id.'/respond', ['decision' => 'accepted'])->assertOk();
        Sanctum::actingAs($this->client);

        return $id;
    }

    public function test_price_offer_requires_opt_in_and_valid_price_currency(): void
    {
        $this->offer()->assertConflict();
        $this->enable();
        $this->offer(['amount_minor' => 0])->assertUnprocessable();
        $this->offer(['amount_minor' => 18500000])->assertUnprocessable();
        $this->offer(['currency' => 'EUR'])->assertUnprocessable();
        Sanctum::actingAs($this->merchant);
        $this->offer()->assertForbidden();
    }

    public function test_offer_has_no_allocation_and_is_idempotent_and_private(): void
    {
        $this->enable();
        $key = (string) Str::uuid();
        $id = $this->offer([], $key)->assertCreated()->json('data.id');
        $this->offer([], $key)->assertOk()->assertJsonPath('data.id', $id);
        $this->offer()->assertConflict();
        $this->assertDatabaseCount('vehicle_blocks', 0);
        $this->assertDatabaseCount('payments', 0);
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/me/price-offers/'.$id)->assertNotFound();
        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/v1/merchant/price-offers/'.$id.'/respond', ['decision' => 'accepted'])->assertNotFound();
    }

    public function test_acceptance_then_purchase_uses_server_price_once_and_demo_payment_matches(): void
    {
        $id = $this->accepted();
        $this->assertDatabaseCount('vehicle_blocks', 0);
        $key = (string) Str::uuid();
        $order = $this->buy(['price_offer_id' => $id, 'expected_price_minor' => 17000000], $key)->assertCreated()->assertJsonPath('data.total_minor', '17000000')->assertJsonPath('data.handover.mode', 'self')->json('data.id');
        $this->buy(['price_offer_id' => $id, 'expected_price_minor' => 17000000], $key)->assertOk()->assertJsonPath('data.id', $order);
        $this->assertDatabaseHas('price_offers', ['id' => $id, 'status' => 'consumed']);
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/orders/'.$order.'/confirm')->assertOk();
        $this->assertDatabaseHas('payments', ['order_id' => $order, 'amount_minor' => 17000000]);
        Sanctum::actingAs($this->client);
        $this->buy(['price_offer_id' => $id])->assertConflict();
    }

    public function test_rejected_unaccepted_expired_foreign_or_stale_offers_cannot_set_purchase_price(): void
    {
        $this->enable();
        $id = $this->offer()->assertCreated()->json('data.id');
        $this->buy(['price_offer_id' => $id])->assertConflict();
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/price-offers/'.$id.'/respond', ['decision' => 'rejected'])->assertOk();
        Sanctum::actingAs($this->client);
        $this->buy(['price_offer_id' => $id])->assertConflict();
        $accepted = $this->accepted();
        Sanctum::actingAs($this->other);
        $this->buy(['price_offer_id' => $accepted])->assertNotFound();
        Sanctum::actingAs($this->client);
        $this->vehicle->increment('version');
        $this->buy(['price_offer_id' => $accepted])->assertConflict();
        PriceOffer::whereKey($accepted)->update(['expires_at' => now()->subMinute()]);
        $this->buy(['price_offer_id' => $accepted])->assertConflict();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_handover_required_and_invalid_past_phone_or_coordinates_rejected(): void
    {
        $this->buy(['handover' => null])->assertUnprocessable();
        foreach ([
            ['mode' => 'bad'], ['scheduled_local' => '2020-01-01T10:00'], ['contact_phone' => '123'],
            ['mode' => 'delivery', 'address' => '', 'city' => ''], ['latitude' => 100, 'longitude' => 3], ['latitude' => 1],
        ] as $bad) {
            $this->buy(['handover' => array_replace($this->purchaseHandover(), $bad)])->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_details_are_private_persisted_and_timezone_is_server_owned(): void
    {
        $this->shop->update(['timezone' => 'Europe/Paris']);
        $handover = array_replace($this->purchaseHandover(), ['mode' => 'delivery', 'scheduled_local' => '2026-10-08T14:30', 'address' => 'Adresse QA', 'city' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01, 'notes' => 'Appeler avant']);
        $id = $this->buy(['handover' => $handover])->assertCreated()->assertJsonPath('data.handover.scheduled_at', '2026-10-08T12:30:00.000000Z')->assertJsonPath('data.handover.contact_phone', '+2250701020304')->json('data.id');
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/me/orders/'.$id)->assertNotFound();
        Sanctum::actingAs($this->merchant);
        $this->getJson('/api/v1/merchant/orders/'.$id)->assertOk()->assertJsonPath('data.handover.address', 'Adresse QA');
        $public = $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug)->assertOk()->json('data');
        $this->assertArrayNotHasKey('handover', $public);
    }

    public function test_proxy_details_and_normal_price_are_preserved(): void
    {
        $handover = array_replace($this->purchaseHandover(), ['mode' => 'proxy', 'contact_name' => 'Mandataire QA']);
        $this->buy(['handover' => $handover])->assertCreated()->assertJsonPath('data.total_minor', '18500000')->assertJsonPath('data.handover.contact_name', 'Mandataire QA')->assertJsonPath('data.handover.address', null);
    }
}
