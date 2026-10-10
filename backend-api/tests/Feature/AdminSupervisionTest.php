<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\City;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSupervisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private User $merchant;

    private Shop $shop;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([LocationSeeder::class, CatalogReferenceSeeder::class]);
        $this->admin = User::factory()->create(['email' => 'qa-admin@bolidemarket.demo']);
        $this->admin->forceFill(['role' => UserRole::ADMIN])->save();
        $this->client = User::factory()->create(['email' => 'qa-client@bolidemarket.demo']);
        $this->merchant = User::factory()->create(['email' => 'qa-merchant@bolidemarket.demo']);
        $profile = app(CreateMerchantProfile::class)->handle($this->merchant, 'Pro QA', 'Pro QA');
        $profile->forceFill(['approval_status' => MerchantApproval::APPROVED])->save();
        $this->merchant->refresh();
        $this->shop = new Shop;
        $this->shop->forceFill(['merchant_id' => $profile->id, 'name' => 'Boutique QA', 'slug' => 'qa-'.Str::ulid(), 'country_code' => 'CI', 'city_id' => City::where('slug', 'abidjan')->value('id'), 'currency_code' => 'XOF', 'timezone' => 'Africa/Abidjan', 'address' => 'Adresse démo', 'status' => 'published', 'is_demo' => true])->save();
        $this->vehicle = new Vehicle;
        $this->vehicle->forceFill(['shop_id' => $this->shop->id, 'reference' => 'QA-'.Str::ulid(), 'slug' => 'qa-'.Str::ulid(), 'title' => 'Toyota RAV4', 'vehicle_model_id' => VehicleModel::where('name', 'RAV4')->value('id'), 'category_id' => Category::where('slug', 'suv')->value('id'), 'year' => 2024, 'condition' => 'used', 'fuel' => 'petrol', 'transmission' => 'automatic', 'description' => 'Véhicule QA', 'is_for_sale' => true, 'is_for_rent' => true, 'sale_price_minor' => 18500000, 'rent_daily_minor' => 45000, 'currency_code' => 'XOF', 'country_code' => 'CI', 'city_id' => $this->shop->city_id, 'publication_status' => 'published', 'published_at' => now(), 'inventory_status' => 'available', 'is_demo' => true, 'version' => 1])->save();
        $this->vehicle->images()->create(['storage_key' => 'qa/photo.jpg', 'position' => 0, 'alt_text' => 'Photo QA', 'is_placeholder' => true]);
        Sanctum::actingAs($this->admin);
    }

    private function act(string $type, int $id, string $action, array $extra = [])
    {
        return $this->postJson('/api/v1/admin/'.$type.'/'.$id.'/actions', ['action' => $action, 'reason' => 'Vérification de démonstration'] + $extra);
    }

    public function test_non_admin_cannot_read_or_write_any_admin_resource(): void
    {
        foreach ([$this->client, $this->merchant] as $user) {
            Sanctum::actingAs($user);
            foreach (['dashboard', 'users', 'merchants', 'shops', 'vehicles', 'reservations', 'orders', 'payments', 'receipts', 'activity', 'reports', 'settings', 'search?q=QA'] as $route) {
                $this->getJson('/api/v1/admin/'.$route)->assertForbidden();
            }
            $this->act('vehicles', $this->vehicle->id, 'suspend')->assertForbidden();
        }
    }

    public function test_guest_is_unauthenticated(): void
    {
        auth()->forgetGuards();
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_is_coherent_and_scopes_do_not_mix(): void
    {
        $d = $this->getJson('/api/v1/admin/dashboard?scope=demo&period=7')->assertOk()->json('data');
        $this->assertSame(1, $d['fleet']['total']);
        $this->assertSame(1, $d['kpis']['clients']);
        $this->assertSame(7, count($d['series']['registrations']));
        $this->assertSame($d['fleet']['total'], array_sum(array_diff_key($d['fleet'], ['total' => 0])));
        $this->getJson('/api/v1/admin/dashboard?scope=real')->assertOk()->assertJsonPath('data.fleet.total', 0);
        $this->getJson('/api/v1/admin/reports?period=365')->assertOk();
        $this->getJson('/api/v1/admin/dashboard?period=13')->assertUnprocessable();
    }

    public function test_users_are_paginated_filtered_and_redacted(): void
    {
        $r = $this->getJson('/api/v1/admin/users?role=customer&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
        $this->assertArrayNotHasKey('password', $r->json('data.0'));
        $this->assertArrayNotHasKey('remember_token', $r->json('data.0'));
        $this->getJson('/api/v1/admin/users/'.$this->client->id)->assertOk()->assertJsonPath('data.counts.favorites', 0);
        $this->getJson('/api/v1/admin/users?per_page=101')->assertUnprocessable();
    }

    public function test_admin_can_approve_a_merchant_and_audits_the_reason(): void
    {
        $this->shop->merchant->forceFill(['approval_status' => 'pending'])->save();
        $this->act('merchants', $this->shop->merchant_id, 'approve')->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('admin_activity_logs', ['admin_id' => $this->admin->id, 'action' => 'merchants.approve', 'reason' => 'Vérification de démonstration']);
        $this->getJson('/api/v1/admin/merchants')->assertOk()->assertJsonPath('data.0.vehicles_count', 1);
    }

    public function test_merchant_suspension_hides_stock_and_blocks_new_commerce(): void
    {
        $this->act('merchants', $this->shop->merchant_id, 'suspend')->assertOk();
        $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug)->assertNotFound();
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/rental-quotes', ['vehicle_id' => $this->vehicle->id, 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()])->assertConflict();
        Sanctum::actingAs($this->admin);
        $this->act('merchants', $this->shop->merchant_id, 'reactivate')->assertOk();
        $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug)->assertOk();
    }

    public function test_user_suspension_revokes_credentials_and_reactivation_works(): void
    {
        $this->client->createToken('QA');
        $this->act('users', $this->client->id, 'suspend')->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertSame(0, $this->client->tokens()->count());
        Sanctum::actingAs($this->client->fresh());
        $this->getJson('/api/v1/me/orders')->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->act('users', $this->client->id, 'reactivate')->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_self_suspension_and_extra_sensitive_fields_are_rejected(): void
    {
        $this->act('users', $this->admin->id, 'suspend')->assertUnprocessable();
        $this->act('users', $this->client->id, 'suspend', ['role' => 'admin', 'password' => 'unsafe', 'amount' => 0, 'ownership' => $this->admin->id])->assertUnprocessable();
        $this->assertNull($this->client->fresh()->disabled_at);
        $this->assertSame(0, AdminActivityLog::count());
        $this->assertSame(UserRole::CLIENT, $this->client->fresh()->role);
    }

    public function test_shop_suspension_blocks_quote_and_cannot_be_undone_by_pro(): void
    {
        $this->act('shops', $this->shop->id, 'suspend')->assertOk();
        $this->getJson('/api/v1/shops/'.$this->shop->slug)->assertNotFound();
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/rental-quotes', ['vehicle_id' => $this->vehicle->id, 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()])->assertConflict();
        Sanctum::actingAs($this->merchant);
        $this->putJson('/api/v1/merchant/shops/'.$this->shop->id, ['status' => 'published', 'name' => 'Boutique QA', 'country_code' => 'CI', 'city_id' => $this->shop->city_id, 'timezone' => 'Africa/Abidjan', 'address' => 'Adresse démo'])->assertUnprocessable();
        Sanctum::actingAs($this->admin);
        $this->act('shops', $this->shop->id, 'reactivate')->assertOk();
    }

    public function test_vehicle_moderation_persists_and_republish_checks_publication_rules(): void
    {
        $this->act('vehicles', $this->vehicle->id, 'unpublish')->assertOk()->assertJsonPath('data.moderation_status', 'suspended');
        $this->getJson('/api/v1/vehicles/'.$this->vehicle->slug)->assertNotFound();
        Sanctum::actingAs($this->merchant);
        $this->patchJson('/api/v1/merchant/vehicles/'.$this->vehicle->id.'/status', ['publication_status' => 'published'])->assertUnprocessable();
        Sanctum::actingAs($this->admin);
        $this->act('vehicles', $this->vehicle->id, 'publish')->assertOk();
        $this->act('vehicles', $this->vehicle->id, 'review')->assertOk()->assertJsonPath('data.moderation_status', 'review');
        $this->act('vehicles', $this->vehicle->id, 'suspend')->assertOk();
        $this->act('vehicles', $this->vehicle->id, 'review')->assertUnprocessable();
        $this->assertSame('suspended', $this->vehicle->fresh()->moderation_status);
        $this->shop->forceFill(['status' => 'suspended'])->save();
        $count = AdminActivityLog::count();
        $this->act('vehicles', $this->vehicle->id, 'publish')->assertUnprocessable();
        $this->assertSame($count, AdminActivityLog::count());
        $this->assertSame('suspended', $this->vehicle->fresh()->moderation_status);
    }

    public function test_missing_reason_has_no_side_effect_and_log_is_readonly(): void
    {
        $this->postJson('/api/v1/admin/vehicles/'.$this->vehicle->id.'/actions', ['action' => 'suspend'])->assertUnprocessable();
        $this->assertSame(0, AdminActivityLog::count());
        $this->getJson('/api/v1/admin/activity')->assertOk();
        $this->deleteJson('/api/v1/admin/activity/1')->assertNotFound();
    }

    public function test_admin_reads_cross_resource_details_but_never_edits_commerce(): void
    {
        Sanctum::actingAs($this->client);
        $orderId = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/orders', ['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO', 'handover' => $this->purchaseHandover()])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->merchant);
        $receipt = $this->postJson('/api/v1/merchant/orders/'.$orderId.'/confirm')->assertOk()->json('data.receipt_reference');
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/orders/'.$orderId)->assertOk()->assertJsonPath('data.receipt_reference', $receipt);
        $this->getJson('/api/v1/admin/vehicles/'.$this->vehicle->id)->assertOk()->assertJsonPath('data.related.orders.count', 1);
        $this->getJson('/api/v1/admin/shops/'.$this->shop->id)->assertOk();
        $this->getJson('/api/v1/admin/payments')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.is_demo', true);
        $this->getJson('/api/v1/admin/receipts/'.$receipt)->assertOk()->assertJsonPath('data.total_minor', '18500000');
        $this->getJson('/api/v1/admin/receipts/'.$receipt.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->patchJson('/api/v1/admin/receipts/'.$receipt, ['total_minor' => 1])->assertStatus(405);
        $this->patchJson('/api/v1/admin/orders/'.$orderId, ['total_minor' => 1])->assertStatus(405);
        $this->getJson('/api/v1/admin/dashboard?scope=demo')->assertOk()->assertJsonPath('data.payments_demo.0.amount_minor', '18500000');
    }

    public function test_admin_can_read_reservations_and_search_all_supported_types(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reservations', ['vehicle_id' => $this->vehicle->id, 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(), 'payment_method' => 'CASH_DEMO'])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/reservations/'.$id)->assertOk()->assertJsonPath('data.client.id', (string) $this->client->id);
        $this->getJson('/api/v1/admin/reservations?user_id='.$this->client->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/admin/search?q=QA')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('data.payments', 'DEMO uniquement');
    }

    public function test_filters_validate_and_deleted_history_remains_readable(): void
    {
        $this->getJson('/api/v1/admin/vehicles?country_code=CI&listing_type=sale&status=available&publication_status=published&moderation_status=clear&shop_id='.$this->shop->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/admin/vehicles?status=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/admin/users/999999')->assertNotFound();
        $this->getJson('/api/v1/admin/lookup?type=shops&q=QA')->assertOk()->assertJsonPath('data.0.name', 'Boutique QA');
        $this->getJson('/api/v1/admin/lookup?type=cities&country_code=CI')->assertOk();
        $this->act('shops', $this->shop->id, 'suspend')->assertOk();
        $this->assertDatabaseHas('vehicles', ['id' => $this->vehicle->id, 'deleted_at' => null]);
    }

    public function test_admin_origin_is_explicitly_allowed_with_credentials(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5177', 'Access-Control-Request-Method' => 'GET'])->options('/api/v1/admin/dashboard')->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5177')->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
