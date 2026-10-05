<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MerchantApproval;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Receipt;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\ReceiptPdfService;
use App\Services\Commerce\ReceiptService;
use App\Services\Commerce\ReservationService;
use Database\Seeders\CatalogReferenceSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceiptTest extends TestCase
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

    private function paidSale(): Receipt
    {
        $order = app(OrderService::class)->create($this->client, ['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO', 'handover' => $this->purchaseHandover()]);
        $this->assertDatabaseCount('receipts', 0);
        app(OrderService::class)->transition($this->merchant, $order, OrderStatus::CONFIRMED, true);

        return Receipt::firstOrFail();
    }

    public function test_sale_is_created_once_after_payment_and_atomic(): void
    {
        $r = $this->paidSale();
        $this->assertSame('sale', $r->type->value);
        $this->assertSame('18500000', $r->total_minor);
        $again = app(ReceiptService::class)->issue($r->payment);
        $this->assertSame($r->id, $again->id);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertStringStartsWith('BM-RCP-2026-', $r->reference);
        $this->assertTrue($r->is_demo);
        $this->assertSame('paid', $r->payment_status);
        $this->getJson('/api/v1/me/orders/'.$r->order_id)->assertJsonPath('data.receipt_reference', $r->reference);
        DB::beginTransaction();
        $copy = $this->vehicle->replicate();
        $copy->slug = 'rollback-sale';
        $copy->reference = 'ROLLBACK';
        $copy->save();
        $o = app(OrderService::class)->create($this->client, ['vehicle_id' => $copy->id, 'payment_method' => 'CASH_DEMO', 'handover' => $this->purchaseHandover()]);
        app(OrderService::class)->transition($this->merchant, $o, OrderStatus::CONFIRMED, true);
        $this->assertDatabaseCount('receipts', 2);
        DB::rollBack();
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_rental_receipt_uses_reservation_dates_and_single_payment_order(): void
    {
        $s = app(ReservationService::class);
        $r = $s->create($this->client, ['vehicle_id' => $this->vehicle->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-09', 'payment_method' => 'MOBILE_MONEY_DEMO']);
        $this->assertDatabaseCount('receipts', 0);
        $s->transition($this->merchant, $r, ReservationStatus::CONFIRMED, true);
        $receipt = Receipt::firstOrFail();
        $this->assertSame('rental', $receipt->type->value);
        $this->assertSame('180000', $receipt->total_minor);
        $this->assertSame(4, $receipt->transaction_snapshot['days']);
        $this->assertSame('45000', $receipt->transaction_snapshot['daily_price_minor']);
        $this->assertSame($r->reference, $receipt->transaction_snapshot['reservation_reference']);
        $this->getJson('/api/v1/me/reservations/'.$r->id)->assertJsonPath('data.receipt_reference', $receipt->reference);
    }

    public function test_snapshots_survive_changes_before_confirmation_and_after_issue(): void
    {
        $original = $this->client->name;
        $s = app(OrderService::class);
        $o = $s->create($this->client, ['vehicle_id' => $this->vehicle->id, 'payment_method' => 'CARD_DEMO', 'handover' => $this->purchaseHandover()]);
        $this->client->update(['first_name' => 'Nouveau prénom']);
        $this->shop->update(['name' => 'Boutique modifiée']);
        $this->vehicle->update(['sale_price_minor' => 20000000]);
        $s->transition($this->merchant, $o, OrderStatus::CONFIRMED, true);
        $r = Receipt::firstOrFail();
        $snapshot = $r->toArray();
        $this->shop->update(['name' => 'Encore modifiée']);
        $this->vehicle->update(['sale_price_minor' => 22000000]);
        $this->assertSame($snapshot, $r->fresh()->toArray());
        $this->getJson('/api/v1/me/receipts/'.$r->reference)->assertOk()->assertJsonPath('data.seller.name', 'Commerce Demo')->assertJsonPath('data.buyer.name', $original)->assertJsonPath('data.total_minor', '18500000')->assertJsonMissingPath('data.buyer.password');
        $this->expectException(\LogicException::class);
        $r->update(['total_minor' => 1]);
    }

    public function test_receipt_access_is_private_for_list_detail_pdf_and_read_only(): void
    {
        $r = $this->paidSale();
        foreach (['', '/pdf'] as $suffix) {
            $this->get('/api/v1/me/receipts/'.$r->reference.$suffix, ['Accept' => 'application/json'])->assertOk();
        }
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/me/receipts')->assertJsonCount(0, 'data');
        foreach (['', '/pdf'] as $suffix) {
            $this->getJson('/api/v1/me/receipts/'.$r->reference.$suffix)->assertNotFound();
        }
        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/v1/merchant/receipts')->assertJsonCount(0, 'data');
        foreach (['', '/pdf'] as $suffix) {
            $this->getJson('/api/v1/merchant/receipts/'.$r->reference.$suffix)->assertNotFound();
        }
        Sanctum::actingAs($this->merchant);
        $this->getJson('/api/v1/merchant/receipts?shop_id='.$this->shop->id)->assertJsonCount(1, 'data');
        foreach (['', '/pdf'] as $suffix) {
            $this->get('/api/v1/merchant/receipts/'.$r->reference.$suffix, ['Accept' => 'application/json'])->assertOk();
        }
        $this->patchJson('/api/v1/merchant/receipts/'.$r->reference, ['total_minor' => 0])->assertStatus(405);
    }

    public function test_pdf_headers_disposition_currency_and_escaped_content(): void
    {
        $this->client->update(['first_name' => 'Éléonore', 'last_name' => 'François']);
        $r = $this->paidSale();
        $response = $this->get('/api/v1/me/receipts/'.$r->reference.'/pdf');
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->get('/api/v1/me/receipts/'.$r->reference.'/pdf?disposition=inline')->assertOk()->assertHeader('Content-Disposition', 'inline; filename="BolideMarket_'.$r->reference.'.pdf"');
        $this->getJson('/api/v1/me/receipts/'.$r->reference.'/pdf?disposition=evil')->assertUnprocessable();
        $html = app(ReceiptPdfService::class)->html($r);
        foreach (['REÇU DE DÉMONSTRATION', 'Aucun paiement réel', '18 500 000 FCFA', 'Éléonore', 'François'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['EUR' => '€', 'USD' => '$', 'CAD' => 'CA$'] as $currency => $symbol) {
            $this->assertSame('12 345,67 '.$symbol, ReceiptPdfService::money('1234567', 2, $currency));
        }
    }

    public function test_cancelled_paid_receipt_remains_unchanged(): void
    {
        $r = $this->paidSale();
        $before = $r->toArray();
        app(OrderService::class)->transition($this->merchant, $r->order, OrderStatus::CANCELLED, true);
        $this->assertSame($before, $r->fresh()->toArray());
        $this->getJson('/api/v1/me/receipts/'.$r->reference)->assertOk();
    }

    public function test_unique_database_constraint_and_deletion_are_guarded(): void
    {
        $r = $this->paidSale();
        try {
            $copy = $r->replicate();
            $copy->reference = 'BM-RCP-DUPLICATE';
            $copy->save();
            $this->fail('Duplicate receipt accepted');
        } catch (QueryException $e) {
            $this->assertSame('23000',$e->getCode());
        }
        $this->expectException(\LogicException::class);
        $r->delete();
    }
}
