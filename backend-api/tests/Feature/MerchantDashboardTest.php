<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private User $outsider;

    private Shop $shop;

    private Shop $otherShop;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([LocationSeeder::class, CatalogReferenceSeeder::class]);
        [$this->merchant,$this->shop] = $this->shop();
        [$this->outsider,$this->otherShop] = $this->shop();
        Sanctum::actingAs($this->merchant);
        $this->vehicle = $this->vehicle($this->shop);
    }

    private function shop(): array
    {
        $user = User::factory()->create();
        $profile = app(CreateMerchantProfile::class)->handle($user, 'QA Pro', 'QA Pro');
        $profile->forceFill(['approval_status' => MerchantApproval::APPROVED])->save();
        $shop = new Shop;
        $shop->forceFill(['merchant_id' => $profile->id, 'name' => 'QA Boutique', 'slug' => 'qa-'.Str::ulid(), 'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->value('id'), 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan', 'address' => 'Adresse démo', 'status' => 'published', 'is_demo' => true])->save();

        return [$user->fresh(), $shop];
    }

    private function vehicle(Shop $shop): Vehicle
    {
        $v = new Vehicle;
        $v->forceFill(['shop_id' => $shop->id, 'reference' => 'QA-'.Str::ulid(), 'slug' => 'qa-'.Str::ulid(), 'title' => 'Toyota RAV4', 'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->value('id'), 'category_id' => Category::where('slug', 'suv')->value('id'), 'year' => 2024, 'condition' => 'used', 'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Véhicule QA', 'is_for_sale' => true, 'is_for_rent' => true, 'sale_price_minor' => 18500000, 'rent_daily_minor' => 45000, 'currency_code' => 'XOF', 'country_code' => 'CI', 'city_id' => $shop->city_id, 'publication_status' => 'published', 'published_at' => now(), 'inventory_status' => 'available', 'is_demo' => true, 'version' => 1])->save();

        return $v;
    }

    private function sale(User $customer, Vehicle $vehicle): string
    {
        Sanctum::actingAs($customer);

        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/orders', ['vehicle_id' => $vehicle->id, 'payment_method' => 'CARD_DEMO'])->assertCreated()->json('data.id');
    }

    public function test_dashboard_is_scoped_and_fleet_is_a_partition(): void
    {
        foreach (['rented', 'sold', 'other'] as $status) {
            $this->vehicle($this->shop)->forceFill(['inventory_status' => $status])->save();
        }
        $this->vehicle($this->otherShop);
        $this->vehicle($this->shop)->delete();
        $this->vehicle($this->shop)->forceFill(['is_demo' => false])->save();
        $this->getJson('/api/v1/merchant/dashboard?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.vehicles_total', 4)
            ->assertJsonPath('data.fleet', ['available' => 1, 'rented' => 1, 'sold' => 1, 'other' => 1])
            ->assertJsonCount(4, 'data.recent_vehicles')->assertJsonCount(6, 'data.activity');
        $this->getJson('/api/v1/merchant/vehicles?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/v1/merchant/dashboard?shop_id='.$this->otherShop->id)->assertNotFound();
        $this->getJson('/api/v1/merchant/clients?shop_id='.$this->otherShop->id)->assertNotFound();
    }

    public function test_customer_cannot_read_pro_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/merchant/dashboard?shop_id='.$this->shop->id)->assertForbidden();
        $this->getJson('/api/v1/merchant/clients?shop_id='.$this->shop->id)->assertForbidden();
    }

    public function test_clients_are_derived_scoped_and_only_necessary_fields_are_exposed(): void
    {
        $client = User::factory()->create();
        $order = $this->sale($client, $this->vehicle);
        $this->sale(User::factory()->create(), $this->vehicle($this->otherShop));
        Sanctum::actingAs($this->merchant);
        $this->getJson('/api/v1/merchant/clients?shop_id='.$this->shop->id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', $client->email)->assertJsonPath('data.0.operations_count', 1)->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.role');
        $this->getJson('/api/v1/merchant/orders/'.$order)->assertOk()->assertJsonPath('data.customer.email', $client->email);
        Sanctum::actingAs($client);
        $this->getJson('/api/v1/me/orders/'.$order)->assertOk()->assertJsonMissingPath('data.customer');
    }

    public function test_sales_chart_counts_only_fulfilled_sales_in_the_six_month_window(): void
    {
        $id = $this->sale(User::factory()->create(), $this->vehicle);
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/orders/'.$id.'/confirm')->assertOk();
        $this->postJson('/api/v1/merchant/orders/'.$id.'/fulfil')->assertOk();
        $this->getJson('/api/v1/merchant/dashboard?shop_id='.$this->shop->id)->assertOk()
            ->assertJsonPath('data.fleet.sold', 1)->assertJsonPath('data.orders_total', 1)->assertJsonPath('data.activity.5.sales', 1);
        Order::find($id)->update(['fulfilled_at' => now()->subMonths(8)]);
        $data = $this->getJson('/api/v1/merchant/dashboard?shop_id='.$this->shop->id)->assertOk()->json('data');
        $this->assertSame(0, array_sum(array_column($data['activity'], 'sales')));
    }

    public function test_cross_merchant_vehicle_reservation_order_and_media_access_is_refused(): void
    {
        $client = User::factory()->create();
        $order = $this->sale($client, $this->vehicle);
        $rental = $this->vehicle($this->shop);
        $reservation = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', ['vehicle_id' => $rental->id, 'start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(), 'payment_method' => 'CASH_DEMO'])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/v1/merchant/vehicles/'.$this->vehicle->id)->assertForbidden();
        $this->getJson('/api/v1/merchant/orders/'.$order)->assertNotFound();
        $this->getJson('/api/v1/merchant/reservations/'.$reservation)->assertNotFound();
        $this->postJson('/api/v1/merchant/reservations/'.$reservation.'/confirm')->assertNotFound();
        $this->postJson('/api/v1/merchant/orders/'.$order.'/confirm')->assertNotFound();
        $this->postJson('/api/v1/merchant/shops/'.$this->shop->id.'/media', ['kind' => 'logo'])->assertForbidden();
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['name' => 'Other'])->assertForbidden();
    }

    public function test_vehicle_filters_remain_scoped_and_combine(): void
    {
        $this->vehicle($this->otherShop);
        $this->vehicle($this->shop)->forceFill(['inventory_status' => 'other', 'is_for_rent' => false])->save();
        $this->getJson('/api/v1/merchant/vehicles?shop_id='.$this->shop->id.'&listing_type=rental&status=available&q=RAV4')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $this->vehicle->id);
        $this->getJson('/api/v1/merchant/vehicles?shop_id='.$this->otherShop->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/merchant/vehicles?status=reserved')->assertUnprocessable();
    }

    public function test_shop_contact_and_hours_can_change_with_sold_vehicle_but_country_cannot(): void
    {
        $this->vehicle->forceFill(['inventory_status' => 'sold'])->save();
        $hours = array_map(fn ($day) => ['day' => $day, 'closed' => false, 'opens' => '09:00', 'closes' => '18:00'], range(1, 7));
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['name' => 'Nouveau nom', 'opening_hours' => $hours])->assertOk()->assertJsonPath('data.name', 'Nouveau nom')->assertJsonPath('data.opening_hours.0.opens', '09:00');
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['country_code' => 'FR', 'city_id' => City::where('slug', 'paris')->value('id'), 'district_id' => null])->assertUnprocessable();
        $this->assertSame('XOF', $this->shop->fresh()->currency_code);
        $hours[0]['closes'] = '08:00';
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['opening_hours' => $hours])->assertUnprocessable();
    }

    public function test_shop_media_is_validated_and_returned_publicly(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVQIHWP4z8AAAAMBAQDHLbzvAAAAAElFTkSuQmCC');
        // Generate a valid CRC-clean raster without a GD dependency.
        $chunk = fn ($type, $bytes) => pack('N', strlen($bytes)).$type.$bytes.hash('crc32b', $type.$bytes, true);
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress("\0\x20\x40\x60")).$chunk('IEND', '');
        $this->postJson('/api/v1/merchant/shops/'.$this->shop->id.'/media', ['kind' => 'cover', 'image' => UploadedFile::fake()->createWithContent('cover.png', $png)])->assertOk()->assertJsonPath('data.slug', $this->shop->slug);
        $this->assertNotNull($this->shop->fresh()->cover_path);
        Storage::disk('public')->assertExists($this->shop->fresh()->cover_path);
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertOk()->assertJsonPath('data.cover_url', Storage::disk('public')->url($this->shop->fresh()->cover_path));
        $this->postJson('/api/v1/merchant/shops/'.$this->shop->id.'/media', ['kind' => 'cover', 'image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')])->assertUnprocessable();
    }

    public function test_rental_client_not_counted_twice_after_confirmation_and_filters_use_real_states(): void
    {
        $client = User::factory()->create();
        Sanctum::actingAs($client);
        $id = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', ['vehicle_id' => $this->vehicle->id, 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(3)->toDateString(), 'payment_method' => 'CASH_DEMO'])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/reservations/'.$id.'/confirm')->assertOk()->assertJsonPath('data.customer.email', $client->email);
        $this->getJson('/api/v1/merchant/clients?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.0.operations_count', 1);
        $this->getJson('/api/v1/merchant/orders?kind=sale')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/merchant/reservations?rentals=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/merchant/reservations?status=confirmed&q='.urlencode($client->email))->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/api/v1/merchant/dashboard?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.pending_reservations',0)->assertJsonPath('data.orders_total',0);
    }
}
