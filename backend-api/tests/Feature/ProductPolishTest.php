<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use App\Services\CurrencyResolver;
use App\Support\DemoMode;
use Database\Seeders\LocationSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocationSeeder::class);
        Notification::fake();
    }

    private function person(array $extra = []): array
    {
        return array_replace(['first_name' => 'Awa', 'last_name' => 'Koné', 'email' => 'polish@example.test', 'country_code' => 'CI', 'phone' => '07 01 02 03 04', 'password' => 'ValidPass123!', 'password_confirmation' => 'ValidPass123!'], $extra);
    }

    private function merchant(string $country = 'CI'): array
    {
        return $this->person() + ['shop' => ['name' => 'Polish Motors', 'activity_type' => 'both', 'address' => 'Adresse de test', 'country_code' => $country, 'city_id' => City::where('country_code', $country)->first()->id, 'timezone' => 'UTC']];
    }

    public function test_demo_registration_normalizes_phone_and_does_not_require_email(): void
    {
        config(['demo.enabled' => true]);
        $this->postJson('/api/v1/auth/register', $this->person())->assertCreated()->assertJsonPath('data.country_code', 'CI')->assertJsonPath('data.phone', '+2250701020304')->assertJsonPath('data.email_verification_required', false);
        Notification::assertNothingSent();
        $this->assertNull(User::sole()->email_verified_at);
    }

    public function test_production_cannot_enable_demo_bypass(): void
    {
        config(['demo.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->assertFalse(DemoMode::enabled());
        $this->postJson('/api/v1/auth/register', $this->person())->assertCreated()->assertJsonPath('data.email_verification_required', true);
        $this->assertFalse(User::sole()->canUseCommerce());
        Notification::assertSentTo(User::sole(), VerifyEmail::class);
    }

    public static function phones(): array
    {
        return [['CI', '0701020304', '+2250701020304'], ['FR', '0612345678', '+33612345678'], ['US', '2025550123', '+12025550123'], ['CA', '4165550123', '+14165550123'], ['SN', '771234567', '+221771234567'], ['BE', '0470123456', '+32470123456']];
    }

    #[DataProvider('phones')]
    public function test_international_phone_and_country(string $country, string $phone, string $expected): void
    {
        $this->postJson('/api/v1/auth/register', $this->person(['country_code' => $country, 'phone' => $phone]))->assertCreated()->assertJsonPath('data.phone', $expected);
    }

    public function test_invalid_phone_wrong_region_and_non_scalar_country_rejected(): void
    {
        foreach ([['phone' => '123'], ['phone' => '+33612345678'], ['country_code' => ['CI']]] as $extra) {
            $this->postJson('/api/v1/auth/register', $this->person($extra))->assertUnprocessable();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_profile_updates_only_current_user_and_rejects_privileged_fields(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $fields = collect($this->person())->only(['first_name', 'last_name', 'phone', 'country_code'])->all();
        $fields['city_id'] = City::where('country_code', 'CI')->first()->id;
        $this->patchJson('/api/v1/me/profile', $fields)->assertOk()->assertJsonPath('data.phone', '+2250701020304')->assertJsonPath('data.city_id', (string) $fields['city_id']);
        $this->assertSame($other->first_name, $other->fresh()->first_name);
        foreach (['email' => 'other@example.test', 'role' => 'admin', 'user_id' => $other->id, 'avatar_path' => 'elsewhere'] as $key => $value) {
            $this->patchJson('/api/v1/me/profile', $fields + [$key => $value])->assertUnprocessable();
        }
        $fields['city_id'] = City::where('country_code', 'FR')->first()->id;
        $this->patchJson('/api/v1/me/profile', $fields)->assertUnprocessable();
    }

    public function test_avatar_upload_and_invalid_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $chunk = fn ($type, $bytes) => pack('N', strlen($bytes)).$type.$bytes.hash('crc32b', $type.$bytes, true);
        // Use a real PNG fixture with valid chunks, without requiring GD.
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress("\0\x20\x40\x60")).$chunk('IEND', '');
        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('avatar.png', $png)])->assertOk()->assertJsonPath('data.avatar_url', fn ($v) => str_contains($v, '/avatars/'.$user->id.'/'));
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
        $old = $user->fresh()->avatar_path;
        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->createWithContent('attack.svg', '<svg onload="alert(1)"/>')])->assertUnprocessable();
        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->create('large.png', 3073, 'image/png')])->assertUnprocessable();
        $this->assertSame($old, $user->fresh()->avatar_path);
    }

    public static function markets(): array
    {
        return [['CI', 'XOF'], ['FR', 'EUR'], ['US', 'USD'], ['CA', 'CAD']];
    }

    #[DataProvider('markets')]
    public function test_merchant_registration_creates_approved_owner_and_shop_currency(string $country, string $currency): void
    {
        config(['demo.enabled' => true]);
        $this->postJson('/api/v1/auth/register-merchant', $this->merchant($country))->assertCreated()->assertJsonPath('data.role', 'merchant')->assertJsonPath('data.merchant.approval_status', 'approved')->assertJsonPath('data.merchant.shops.0.currency_code', $currency);
        $this->assertDatabaseCount('merchant_profiles', 1);
        $this->assertDatabaseCount('merchant_memberships', 1);
        $this->assertDatabaseCount('shops', 1);
        $this->assertTrue(Shop::sole()->is_demo);
        $this->assertSame($currency, app(CurrencyResolver::class)->forShop(Shop::sole(), []));
    }

    public function test_production_merchant_is_pending_even_when_demo_flag_true(): void
    {
        config(['demo.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->postJson('/api/v1/auth/register-merchant', $this->merchant())->assertCreated()->assertJsonPath('data.merchant.approval_status', 'pending')->assertJsonPath('data.merchant.shops.0.status', 'draft');
        $this->assertFalse(Shop::sole()->is_demo);
    }

    public function test_merchant_cannot_escalate_role_or_override_market_currency(): void
    {
        foreach ([['role' => 'admin'], ['approval_status' => 'approved'], ['is_demo' => true]] as $extra) {
            $this->postJson('/api/v1/auth/register-merchant', $this->merchant() + $extra)->assertUnprocessable();
        }
        $data = $this->merchant();
        $data['shop']['currency_code'] = 'EUR';
        $this->postJson('/api/v1/auth/register-merchant', $data)->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('shops', 0);
    }

    public function test_merchant_registration_rolls_back_when_currency_unavailable(): void
    {
        Currency::where('code', 'XOF')->update(['active' => false]);
        $this->postJson('/api/v1/auth/register-merchant', $this->merchant())->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('merchant_profiles', 0);
        $this->assertDatabaseCount('merchant_memberships', 0);
    }

    public function test_currency_override_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(CurrencyResolver::class)->forCountry('CI', 'EUR');
    }

    public function test_anonymous_profile_and_avatar_denied(): void
    {
        $this->patchJson('/api/v1/me/profile', [])->assertUnauthorized();
        $this->postJson('/api/v1/me/avatar', [])->assertUnauthorized();
    }
}
