<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\InventoryStatus;
use App\Enums\MerchantApproval;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBlock;
use App\Models\VehicleModel;
use App\Services\Commerce\DemoPaymentService;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\ClientCommerceDemoSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientCommerceTest extends TestCase
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

    public function test_demo_email_bypass_allows_purchase_without_marking_email_verified(): void
    {
        config(['demo.enabled' => true]);
        $this->client->forceFill(['email_verified_at' => null])->save();
        $this->buy()->assertCreated();
        $this->assertNull($this->client->fresh()->email_verified_at);
    }

    public function test_production_still_blocks_unverified_purchase_when_demo_flag_true(): void
    {
        config(['demo.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->client->forceFill(['email_verified_at' => null])->save();
        $this->buy()->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    private function reserve(array $extra = [], ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/reservations', array_replace(['vehicle_id' => $this->vehicle->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-09', 'payment_method' => 'MOBILE_MONEY_DEMO'], $extra));
    }

    private function buy(array $extra = [], ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/orders', array_replace(['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO'], $extra));
    }

    private function merchantReservation(string $id, string $action)
    {
        Sanctum::actingAs($this->merchant);

        return $this->postJson('/api/v1/merchant/reservations/'.$id.'/'.$action);
    }

    public function test_favorites_are_unique_private_removable_and_resource_compatible(): void
    {
        $path = '/api/v1/me/favorites/'.$this->vehicle->id;
        $this->putJson($path)->assertOk()->assertJsonPath('data.model.name', 'RAV4');
        $this->putJson($path)->assertOk();
        $this->assertSame(1, Favorite::count());
        $this->getJson('/api/v1/me/favorites')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.sale_price.amount_minor', '18500000');
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/me/favorites')->assertJsonPath('meta.total', 0);
        $this->deleteJson($path)->assertNoContent();
        $this->assertSame(1, Favorite::count());
        Sanctum::actingAs($this->client);
        $this->deleteJson($path)->assertNoContent();
        $this->deleteJson($path)->assertNoContent();
        $this->assertSame(0, Favorite::count());
    }

    public function test_anonymous_and_unverified_operations_are_refused(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me/favorites')->assertUnauthorized();
        $this->postJson('/api/v1/reservations')->assertUnauthorized();
        $this->client->email_verified_at = null;
        $this->client->save();
        Sanctum::actingAs($this->client);
        $this->reserve()->assertForbidden();
        $this->buy()->assertForbidden();
    }

    public function test_reservation_recalculates_amount_and_preserves_current_inventory(): void
    {
        $response = $this->reserve(['daily_price' => 1, 'total_minor' => 1, 'shop_id' => 999, 'user_id' => $this->other->id, 'currency' => 'EUR']);
        $response->assertCreated()->assertJsonPath('data.billable_days', 4)->assertJsonPath('data.total_minor', '180000')->assertJsonPath('data.currency', 'XOF')->assertJsonPath('data.status', 'pending')->assertJsonPath('data.is_demo', true);
        $this->assertSame($this->client->id, Reservation::first()->user_id);
        $this->assertSame(InventoryStatus::AVAILABLE, $this->vehicle->fresh()->inventory_status);
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, VehicleBlock::count());
    }

    public static function invalidReservations(): array
    {
        return [[['start_date' => '2026-09-30']], [['end_date' => '2026-10-05']], [['end_date' => '2026-10-04']], [['start_date' => '2026-02-30']], [['payment_method' => 'CARD']], [['end_date' => '2028-10-09']]];
    }

    #[DataProvider('invalidReservations')]
    public function test_invalid_reservation_input_is_rejected(array $extra): void
    {
        $this->reserve($extra)->assertUnprocessable();
        $this->assertSame(0, Reservation::count());
    }

    public function test_wrong_offer_sold_private_and_non_demo_vehicles_are_refused(): void
    {
        $this->vehicle->is_for_rent = false;
        $this->vehicle->save();
        $this->reserve()->assertConflict();
        $this->vehicle->is_for_rent = true;
        $this->vehicle->is_for_sale = false;
        $this->vehicle->save();
        $this->buy()->assertConflict();
        $this->vehicle->is_for_sale = true;
        $this->vehicle->inventory_status = InventoryStatus::SOLD;
        $this->vehicle->save();
        $this->buy()->assertConflict();
        $this->vehicle->inventory_status = InventoryStatus::AVAILABLE;
        $this->vehicle->publication_status = 'draft';
        $this->vehicle->save();
        $this->reserve()->assertConflict();
        $this->vehicle->publication_status = 'published';
        $this->vehicle->is_demo = false;
        $this->vehicle->save();
        $this->buy()->assertForbidden();
    }

    public function test_pending_and_confirmed_periods_block_but_adjacent_periods_do_not(): void
    {
        $id = $this->reserve()->assertCreated()->json('data.id');
        Sanctum::actingAs($this->other);
        $this->reserve(['start_date' => '2026-10-08', 'end_date' => '2026-10-10'])->assertConflict();
        $this->reserve(['start_date' => '2026-10-09', 'end_date' => '2026-10-10'])->assertCreated();
        $this->merchantReservation($id, 'confirm')->assertOk()->assertJsonPath('data.status', 'confirmed');
        Sanctum::actingAs($this->other);
        $this->reserve()->assertConflict();
        $this->buy()->assertConflict();
        $this->assertSame(2, Reservation::count());
    }

    public function test_expired_hold_does_not_require_scheduler_and_cannot_be_confirmed(): void
    {
        $id = $this->reserve()->json('data.id');
        $this->travel(16)->minutes();
        $this->getJson('/api/v1/me/reservations/'.$id)->assertJsonPath('data.status', 'expired');
        $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug.'/availability')->assertJsonPath('meta.total', 0);
        Sanctum::actingAs($this->merchant);
        $this->getJson('/api/v1/merchant/reservations?status=pending')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/merchant/reservations?status=expired')->assertJsonPath('meta.total', 1);
        $this->merchantReservation($id, 'confirm')->assertConflict();
        Sanctum::actingAs($this->other);
        $this->reserve()->assertCreated();
    }

    public function test_customer_and_merchant_isolation_on_reservations(): void
    {
        $id = $this->reserve()->json('data.id');
        $this->getJson('/api/v1/me/reservations')->assertJsonPath('meta.total', 1);
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/me/reservations/'.$id)->assertNotFound();
        $this->postJson('/api/v1/me/reservations/'.$id.'/cancel')->assertNotFound();
        $this->getJson('/api/v1/me/reservations')->assertJsonPath('meta.total', 0);
        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/v1/merchant/reservations?shop_id='.$this->shop->id)->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/merchant/reservations/'.$id.'/confirm')->assertNotFound();
        Sanctum::actingAs($this->merchant);
        $this->getJson('/api/v1/merchant/reservations')->assertJsonPath('meta.total', 1);
    }

    public function test_confirmed_cancellation_releases_calendar_and_preserves_demo_payment_audit(): void
    {
        $id = $this->reserve()->json('data.id');
        $this->merchantReservation($id, 'confirm')->assertOk()->assertJsonPath('data.order.payment.is_demo', true);
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/me/reservations/'.$id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.order.status', 'cancelled');
        $this->postJson('/api/v1/me/reservations/'.$id.'/cancel')->assertConflict();
        $this->reserve()->assertCreated();
        $this->assertDatabaseHas('payments', ['status' => 'paid', 'is_demo' => true]);
    }

    public function test_active_and_completed_reservations_cannot_be_cancelled(): void
    {
        $id = $this->reserve(['start_date' => '2026-10-01', 'end_date' => '2026-10-03'])->json('data.id');
        $this->merchantReservation($id, 'confirm')->assertOk();
        $this->merchantReservation($id, 'start')->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertSame(InventoryStatus::RENTED, $this->vehicle->fresh()->inventory_status);
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/me/reservations/'.$id.'/cancel')->assertConflict();
        $this->merchantReservation($id, 'complete')->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.order.status', 'fulfilled');
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/me/reservations/'.$id.'/cancel')->assertConflict();
        $this->assertSame(InventoryStatus::AVAILABLE, $this->vehicle->fresh()->inventory_status);
    }

    public function test_sale_is_recalculated_then_confirmed_paid_and_fulfilled_only_once(): void
    {
        $id = $this->buy(['total_minor' => 1])->assertCreated()->assertJsonPath('data.total_minor', '18500000')->json('data.id');
        Sanctum::actingAs($this->other);
        $this->buy()->assertConflict();
        $this->reserve()->assertConflict();
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/orders/'.$id.'/confirm')->assertOk()->assertJsonPath('data.payment.status', 'paid')->assertJsonPath('data.payment.is_demo', true);
        $this->postJson('/api/v1/merchant/orders/'.$id.'/fulfil')->assertOk()->assertJsonPath('data.status', 'fulfilled');
        $this->assertSame(InventoryStatus::SOLD, $this->vehicle->fresh()->inventory_status);
        Sanctum::actingAs($this->client);
        $this->buy()->assertConflict();
        $this->getJson('/api/v1/vehicles?status=available')->assertJsonPath('meta.total', 0);
        $this->assertSame(1, Payment::count());
        Http::assertNothingSent();
    }

    public function test_orders_are_private_for_customers_and_merchant_memberships(): void
    {
        $id = $this->buy()->json('data.id');
        $this->getJson('/api/v1/me/orders')->assertJsonPath('meta.total', 1);
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/me/orders/'.$id)->assertNotFound();
        $this->postJson('/api/v1/me/orders/'.$id.'/cancel')->assertNotFound();
        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/v1/merchant/orders')->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/merchant/orders/'.$id.'/confirm')->assertNotFound();
        Sanctum::actingAs($this->merchant);
        $this->getJson('/api/v1/merchant/orders')->assertJsonPath('meta.total', 1);
    }

    public function test_idempotency_replays_and_rejects_changed_input(): void
    {
        $key = (string) Str::uuid();
        $id = $this->buy([], $key)->assertCreated()->json('data.id');
        $this->buy([], $key)->assertOk()->assertJsonPath('data.id', $id);
        $this->buy(['payment_method' => 'CASH_DEMO'], $key)->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(1, Order::count());
        $this->assertSame(1, VehicleBlock::count());
    }

    public function test_quote_expiry_price_change_and_reuse_are_checked(): void
    {
        $input = ['vehicle_id' => $this->vehicle->id, 'starts_at' => '2026-10-05T10:00:00Z', 'ends_at' => '2026-10-06T10:00:01Z'];
        $quote = $this->postJson('/api/v1/rental-quotes', $input)->assertCreated()->assertJsonPath('data.billable_days', 2)->json('data.id');
        $this->vehicle->rent_daily_minor = 50000;
        $this->vehicle->version++;
        $this->vehicle->save();
        $payload = ['quote_id' => $quote, 'payment_method' => 'CASH_DEMO'];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', $payload)->assertConflict()->assertJsonPath('error.code', 'PRICE_CHANGED');
        $quote = $this->postJson('/api/v1/rental-quotes', $input)->assertCreated()->json('data.id');
        $this->travel(6)->minutes();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', ['quote_id' => $quote, 'payment_method' => 'CASH_DEMO'])->assertConflict()->assertJsonPath('error.code', 'QUOTE_EXPIRED');
        $this->postJson('/api/v1/rental-quotes', ['vehicle_id' => $this->vehicle->id, 'quote_id' => $quote])->assertUnprocessable();
    }

    public function test_availability_is_public_bounded_and_contains_no_private_ids(): void
    {
        $this->reserve()->assertCreated();
        $response = $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug.'/availability?from=2026-10-01&to=2026-11-01')->assertOk()->assertJsonPath('meta.total', 1);
        $interval = $response->json('data.intervals.0');
        $this->assertSame(['starts_at', 'ends_at', 'expires_at'], array_keys($interval));
        $this->assertStringNotContainsString($this->client->email, $response->getContent());
        $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug.'/availability?from=2026-10-01&to=2029-01-01')->assertUnprocessable();
    }

    public function test_invalid_payment_and_production_simulation_are_refused(): void
    {
        $this->buy(['payment_method' => 'stripe'])->assertUnprocessable();
        $this->buy(['expected_price_minor' => '1'])->assertConflict()->assertJsonPath('error.code', 'PRICE_CHANGED');
        $this->app['env'] = 'production';
        $this->buy()->assertForbidden();
        $this->assertSame(0, Order::count());
    }

    public function test_payment_failure_rolls_back_order_confirmation_and_allocation(): void
    {
        $id = $this->buy()->json('data.id');
        $this->mock(DemoPaymentService::class)->shouldReceive('pay')->once()->andThrow(new \RuntimeException('simulation failed'));
        Sanctum::actingAs($this->merchant);
        $this->postJson('/api/v1/merchant/orders/'.$id.'/confirm')->assertStatus(500);
        $this->assertSame(OrderStatus::PENDING, Order::find($id)->status);
        $this->assertDatabaseHas('vehicle_blocks', ['order_id' => $id, 'kind' => 'hold']);
        $this->assertSame(0, Payment::count());
    }

    public function test_committed_engagement_prevents_catalogue_mutation_and_shop_deletion(): void
    {
        $this->reserve()->assertCreated();
        Sanctum::actingAs($this->merchant);
        $this->patchJson('/api/v1/merchant/vehicles/'.$this->vehicle->id.'/status', ['inventory_status' => 'other'])->assertConflict();
        $this->deleteJson('/api/v1/merchant/vehicles/'.$this->vehicle->id)->assertConflict();
        $this->deleteJson('/api/v1/merchant/shops/'.$this->shop->id)->assertConflict();
    }

    public function test_vehicle_parent_lock_blocks_a_second_mysql_session(): void
    {
        // Two distinct MySQL sessions: a competing allocation cannot obtain the parent row.
        DB::table('vehicles')->where('id', $this->vehicle->id)->lockForUpdate()->first();
        config(['database.connections.competitor' => config('database.connections.mysql')]);
        $competitor = DB::connection('competitor');
        try {
            $competitor->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $competitor->beginTransaction();
            $competitor->table('vehicles')->where('id', $this->vehicle->id)->lockForUpdate()->first();
            $this->fail('The competing session acquired a locked vehicle.');
        } catch (QueryException $error) {
            $this->assertSame(1205, (int) $error->errorInfo[1]);
        } finally {
            if ($competitor->transactionLevel()) {
                $competitor->rollBack();
            }
            DB::purge('competitor');
        }
    }

    public function test_quote_is_private_consumed_once_and_reservation_retry_is_idempotent(): void
    {
        $quote = $this->postJson('/api/v1/rental-quotes', ['vehicle_id' => $this->vehicle->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-09'])->assertCreated()->json('data.id');
        $input = ['quote_id' => $quote, 'payment_method' => 'BANK_TRANSFER_DEMO'];
        Sanctum::actingAs($this->other);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', $input)->assertNotFound();
        Sanctum::actingAs($this->client);
        $key = (string) Str::uuid();
        $id = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/reservations', $input)->assertCreated()->json('data.id');
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/reservations', $input)->assertOk()->assertJsonPath('data.id', $id);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', $input)->assertConflict()->assertJsonPath('error.code', 'QUOTE_EXPIRED');
        $this->assertSame(1, Reservation::count());
    }

    public function test_expired_sale_releases_hold_and_pending_cancellation_is_final(): void
    {
        $id = $this->buy()->assertCreated()->json('data.id');
        $this->travel(16)->minutes();
        $this->getJson('/api/v1/me/orders/'.$id)->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancellation_reason', 'expired');
        Sanctum::actingAs($this->other);
        $next = $this->buy()->assertCreated()->json('data.id');
        $this->postJson('/api/v1/me/orders/'.$next.'/cancel')->assertOk();
        $this->postJson('/api/v1/me/orders/'.$next.'/cancel')->assertConflict();
        $r = $this->reserve()->assertCreated()->json('data.id');
        $this->postJson('/api/v1/me/reservations/'.$r.'/cancel')->assertOk();
        $this->assertSame(0, VehicleBlock::blocking()->count());
    }

    public static function overlappingDates(): array
    {
        return [['2026-10-05', '2026-10-09'], ['2026-10-06', '2026-10-07'], ['2026-10-04', '2026-10-10'], ['2026-10-04', '2026-10-06']];
    }

    #[DataProvider('overlappingDates')]
    public function test_all_overlap_shapes_are_rejected(string $start, string $end): void
    {
        $this->reserve()->assertCreated();
        Sanctum::actingAs($this->other);
        $this->reserve(['start_date' => $start, 'end_date' => $end])->assertConflict();
    }

    public function test_disabled_clients_hidden_favorites_and_missing_idempotency_key(): void
    {
        $this->postJson('/api/v1/orders', ['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO'])->assertUnprocessable();
        $this->putJson('/api/v1/me/favorites/'.$this->vehicle->id)->assertOk();
        $this->vehicle->publication_status = 'draft';
        $this->vehicle->save();
        $this->getJson('/api/v1/me/favorites')->assertJsonPath('meta.total', 0);
        $this->client->disabled_at = now();
        $this->client->save();
        $this->buy()->assertForbidden();
    }

    public function test_demo_seed_is_small_idempotent_and_production_guarded(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);
        $before = Vehicle::count();
        $this->seed(ClientCommerceDemoSeeder::class);
        $this->seed(ClientCommerceDemoSeeder::class);
        $this->assertSame($before + 2, Vehicle::count());
        $this->assertSame(2, Order::count());
        $this->assertSame(1, Reservation::count());
        $this->assertSame(2, Payment::where('is_demo', true)->count());
        $this->assertSame(2, Favorite::count());
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        app(ClientCommerceDemoSeeder::class)->run();
    }

    public function test_start_requires_current_period_and_overdue_active_rental_protects_shop(): void
    {
        $id = $this->reserve()->assertCreated()->json('data.id');
        $this->merchantReservation($id, 'confirm')->assertOk();
        $this->merchantReservation($id, 'start')->assertConflict();
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->merchantReservation($id, 'start')->assertOk();
        $this->travel(10)->days();
        $this->deleteJson('/api/v1/merchant/shops/'.$this->shop->id)->assertConflict();
        $this->merchantReservation($id, 'complete')->assertOk();
    }
}
