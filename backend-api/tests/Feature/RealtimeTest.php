<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\Realtime\OrderCompleted;
use App\Events\Realtime\OrderCreated;
use App\Events\Realtime\ReservationConfirmed;
use App\Events\Realtime\ReservationCreated;
use App\Events\Realtime\VehicleImageUpdated;
use App\Events\Realtime\VehicleStatusChanged;
use App\Events\Realtime\VehicleUnpublished;
use App\Events\Realtime\VehicleUpdated;
use App\Models\Category;
use App\Models\City;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ReservationService;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use DatabaseMigrations;

    private User $merchant;

    private User $customer;

    private Shop $shop;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LocationSeeder::class, CatalogReferenceSeeder::class]);
        $this->merchant = User::factory()->create();
        $profile = app(CreateMerchantProfile::class)->handle($this->merchant, 'QA Realtime', 'QA Realtime');
        $profile->forceFill(['approval_status' => MerchantApproval::APPROVED])->save();
        $this->merchant = $this->merchant->fresh();
        $this->customer = User::factory()->create();
        $this->shop = new Shop;
        $this->shop->forceFill(['merchant_id' => $profile->id, 'name' => 'QA', 'slug' => 'qa-'.Str::ulid(), 'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->value('id'), 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan', 'address' => 'Demo', 'status' => 'published', 'is_demo' => true])->save();
        $this->vehicle = new Vehicle;
        $this->vehicle->forceFill(['shop_id' => $this->shop->id, 'reference' => 'QA-'.Str::ulid(), 'slug' => 'qa-'.Str::ulid(), 'title' => 'Toyota RAV4', 'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->value('id'), 'category_id' => Category::where('slug', 'suv')->value('id'), 'year' => 2024, 'condition' => 'used', 'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Demo', 'is_for_sale' => true, 'is_for_rent' => true, 'sale_price_minor' => 18500000, 'rent_daily_minor' => 45000, 'currency_code' => 'XOF', 'country_code' => 'CI', 'city_id' => $this->shop->city_id, 'publication_status' => 'published', 'published_at' => now(), 'inventory_status' => 'available', 'is_demo' => true, 'version' => 1])->save();
        $this->vehicle = $this->vehicle->fresh();
    }

    public function test_vehicle_event_waits_for_outer_commit_and_has_only_public_identifiers(): void
    {
        Event::fake([VehicleUpdated::class]);
        DB::beginTransaction();
        DB::transaction(fn () => $this->vehicle->update(['sale_price_minor' => 19000000]));
        Event::assertNotDispatched(VehicleUpdated::class);
        DB::commit();
        Event::assertDispatchedTimes(VehicleUpdated::class, 2);
        Event::assertDispatched(VehicleUpdated::class, function ($e) {
            $this->assertSame(['event_id', 'type', 'occurred_at', 'data'], array_keys($e->broadcastWith()));
            $this->assertSame(['vehicle_id', 'slug', 'shop_id', 'version', 'changed_fields'], array_keys($e->payload['data']));
            $this->assertTrue(Str::isUuid($e->payload['event_id']));
            $this->assertStringNotContainsString($this->customer->email, json_encode($e->payload));

            return $e->broadcastOn()[0]->name === 'marketplace';
        });
    }

    public function test_rollback_and_nested_rollback_emit_nothing(): void
    {
        Event::fake([VehicleUpdated::class]);
        DB::beginTransaction();
        DB::beginTransaction();
        $this->vehicle->update(['sale_price_minor' => 19000000]);
        DB::rollBack();
        DB::commit();
        Event::assertNotDispatched(VehicleUpdated::class);
        DB::beginTransaction();
        $this->vehicle->fresh()->update(['sale_price_minor' => 19500000]);
        DB::rollBack();
        Event::assertNotDispatched(VehicleUpdated::class);
    }

    public function test_unpublish_has_a_public_tombstone_but_draft_updates_stay_private(): void
    {
        Event::fake([VehicleUnpublished::class, VehicleUpdated::class]);
        DB::transaction(fn () => $this->vehicle->forceFill(['publication_status' => 'draft'])->save());
        Event::assertDispatched(VehicleUnpublished::class, fn ($e) => $e->broadcastOn()[0]->name === 'marketplace');
        DB::transaction(fn () => $this->vehicle->fresh()->update(['sale_price_minor' => 19000000]));
        Event::assertDispatchedTimes(VehicleUpdated::class, 1);
        Event::assertDispatched(VehicleUpdated::class, fn ($e) => $e->broadcastOn()[0]->name === 'private-merchant.'.$this->shop->merchant_id);
    }

    public function test_reservation_and_order_events_target_only_owner_and_merchant_after_commit(): void
    {
        Event::fake([ReservationCreated::class, ReservationConfirmed::class, OrderCreated::class]);
        DB::beginTransaction();
        $reservation = app(ReservationService::class)->create($this->customer, ['vehicle_id' => $this->vehicle->id, 'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(5)->toDateString(), 'payment_method' => 'CASH_DEMO']);
        Event::assertNotDispatched(ReservationCreated::class);
        DB::commit();
        Event::assertDispatched(ReservationCreated::class, function ($e) {
            $this->assertSame(['private-merchant.'.$this->shop->merchant_id, 'private-user.'.$this->customer->id], array_map(fn ($c) => $c->name, $e->broadcastOn()));
            $this->assertSame(['id', 'shop_id', 'vehicle_id', 'status', 'version'], array_keys($e->payload['data']));

            return true;
        });
        app(ReservationService::class)->transition($this->merchant, $reservation, ReservationStatus::CONFIRMED, true);
        Event::assertDispatched(ReservationConfirmed::class);
        Event::assertDispatched(OrderCreated::class);
    }

    public function test_fulfilled_sale_emits_order_completed_and_public_sold_signal(): void
    {
        Event::fake([OrderCompleted::class, VehicleStatusChanged::class]);
        $order = app(OrderService::class)->create($this->customer, ['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO', 'handover' => $this->purchaseHandover()]);
        app(OrderService::class)->transition($this->merchant, $order, OrderStatus::CONFIRMED, true);
        app(OrderService::class)->transition($this->merchant, $order->fresh(), OrderStatus::FULFILLED, true);
        Event::assertDispatched(OrderCompleted::class);
        Event::assertDispatched(VehicleStatusChanged::class, fn ($e) => $e->broadcastOn()[0]->name === 'marketplace');
        $this->assertSame('sold', $this->vehicle->fresh()->inventory_status->value);
    }

    public function test_broadcast_failure_does_not_undo_committed_write(): void
    {
        Event::listen(VehicleUpdated::class, fn () => throw new \RuntimeException('Socket unavailable'));
        DB::transaction(fn () => $this->vehicle->update(['sale_price_minor' => 19000000]));
        $this->assertSame('19000000', $this->vehicle->fresh()->sale_price_minor);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_images_publish_only_after_commit_and_deletion_refreshes_the_public_cover(): void
    {
        Event::fake([VehicleImageUpdated::class]);
        DB::beginTransaction();
        $image = $this->vehicle->images()->create(['storage_key' => 'vehicles/test.webp', 'position' => 0]);
        Event::assertNotDispatched(VehicleImageUpdated::class);
        DB::commit();
        Event::assertDispatchedTimes(VehicleImageUpdated::class, 2);
        DB::transaction(fn () => $image->delete());
        Event::assertDispatchedTimes(VehicleImageUpdated::class, 4);
        DB::beginTransaction();
        $this->vehicle->images()->create(['storage_key' => 'vehicles/rollback.webp', 'position' => 0]);
        DB::rollBack();
        Event::assertDispatchedTimes(VehicleImageUpdated::class, 4);
    }

    public function test_private_channel_authorization_rejects_other_accounts_guests_and_disabled_users(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-id']);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
        $auth = fn ($channel) => $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => $channel]);
        $auth('private-user.'.$this->customer->id)->assertUnauthorized();
        Sanctum::actingAs($this->merchant);
        $auth('private-merchant.'.$this->shop->merchant_id)->assertOk()->assertJsonStructure(['auth']);
        $auth('private-merchant.999999')->assertForbidden();
        $other = User::factory()->create();
        $otherMerchant = app(CreateMerchantProfile::class)->handle($other, 'Other', 'Other');
        $auth('private-merchant.'.$otherMerchant->id)->assertForbidden();
        $auth('private-user.'.$this->customer->id)->assertForbidden();
        Sanctum::actingAs($this->customer);
        $auth('private-user.'.$this->customer->id)->assertOk();
        $auth('private-user.'.$other->id)->assertForbidden();
        $auth('private-merchant.'.$this->shop->merchant_id)->assertForbidden();
        $this->customer->forceFill(['disabled_at' => now()])->save();
        $auth('private-user.'.$this->customer->id)->assertForbidden();
    }
}
