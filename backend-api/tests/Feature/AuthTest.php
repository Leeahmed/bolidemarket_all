<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(LocationSeeder::class);
    }

    private function registration(array $override = []): array
    {
        return array_replace([
            'first_name' => 'Awa', 'last_name' => 'Koné', 'email' => 'awa@example.test',
            'country_code' => 'CI', 'phone' => '+2250700000001', 'password' => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ], $override);
    }

    public function test_client_registration_hashes_password_and_exposes_only_safe_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'AWA@example.test']));
        $response->assertCreated()->assertJsonPath('data.role', 'customer')
            ->assertJsonPath('data.name', 'Awa Koné')->assertJsonPath('data.email', 'awa@example.test')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $user = User::sole();
        $this->assertTrue(Hash::check('ValidPass123!', $user->password));
        $this->assertNotSame('ValidPass123!', $user->password);
        $this->assertIsString($response->json('data.id'));
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        User::factory()->create(['email' => 'awa@example.test']);
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'AWA@example.test']))
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['email']], 'request_id']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_public_registration_rejects_admin_and_merchant(): void
    {
        foreach (['admin', 'ADMIN', 'merchant'] as $role) {
            $this->postJson('/api/v1/auth/register', $this->registration(['role' => $role]))
                ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['role']]]);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_validation_rejects_weak_password_bad_phone_and_missing_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration([
            'phone' => '0700', 'password' => 'password', 'password_confirmation' => '',
        ]))->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['phone', 'password']]]);
    }

    public function test_registration_rejects_server_controlled_fields(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration(['email_verified_at' => now()->toISOString()]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_privileged_fields_are_not_mass_assignable(): void
    {
        $user = new User;
        $user->fill(['role' => 'admin', 'email_verified_at' => now(), 'disabled_at' => now(), 'remember_token' => 'x']);
        $this->assertArrayNotHasKey('role', $user->getAttributes());
        $this->assertArrayNotHasKey('email_verified_at', $user->getAttributes());
        $this->assertArrayNotHasKey('disabled_at', $user->getAttributes());
        $this->assertArrayNotHasKey('remember_token', $user->getAttributes());
    }

    public function test_api_login_issues_expiring_hashed_token_and_me_accepts_it(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
        $response->assertOk()->assertJsonPath('data.token_type', 'Bearer')->assertJsonMissingPath('data.user.password');
        $token = $response->json('data.token');
        $this->assertNotSame($token, $user->tokens()->sole()->token);
        $this->assertNotNull($user->tokens()->sole()->expires_at);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', (string) $user->id);
    }

    public function test_unknown_email_and_wrong_password_have_identical_errors(): void
    {
        $user = User::factory()->create();
        $wrong = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->json('error');
        $unknown = $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])
            ->assertUnprocessable()->json('error');
        $this->assertSame($wrong, $unknown);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_without_token_returns_json_401_even_without_accept_header(): void
    {
        $this->get('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;
        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = User::factory()->create()->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_disabled_user_cannot_login_or_use_existing_token(): void
    {
        $user = User::factory()->create(['disabled_at' => now()]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $token = $user->createToken('old')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests()->assertJsonPath('error.code', 'RATE_LIMITED')->assertHeader('Retry-After');
    }

    public function test_spa_login_uses_session_without_issuing_token_and_logout_invalidates_it(): void
    {
        $user = User::factory()->create();
        $this->withHeader('Origin', 'http://localhost:5174')
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.id', (string) $user->id)->assertJsonMissingPath('data.token');
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_stateful_login_requires_csrf_outside_test_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->withHeader('Origin', 'http://localhost:5174')->postJson('/api/v1/auth/login', [])
            ->assertStatus(419)->assertJsonPath('error.code', 'CSRF_MISMATCH');
    }

    public function test_cors_allows_known_origin_and_rejects_unknown_origin(): void
    {
        $this->options('/api/v1/reservations', [], [
            'Origin' => 'http://127.0.0.1:5174',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-xsrf-token,idempotency-key',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5174')
            ->assertHeader('Access-Control-Allow-Headers', 'accept, authorization, content-type, x-xsrf-token, x-csrf-token, x-requested-with, idempotency-key');
        $this->options('/api/v1/auth/login', [], ['Origin' => 'http://localhost:5174', 'Access-Control-Request-Method' => 'POST'])
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5174')->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->options('/api/v1/auth/login', [], ['Origin' => 'https://unknown.example', 'Access-Control-Request-Method' => 'POST'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_forgot_password_response_does_not_reveal_account_existence(): void
    {
        $user = User::factory()->create();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_revokes_tokens_and_cannot_reuse_link(): void
    {
        $user = User::factory()->create();
        $user->createToken('old');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $notification = Notification::sent($user, ResetPassword::class)->sole();
        $payload = ['email' => $user->email, 'token' => $notification->token,
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_signed_verification_link_verifies_only_authenticated_owner(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->withToken(User::factory()->create()->createToken('other')->plainTextToken)->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('owner')->plainTextToken)->getJson($url)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_health_endpoint_is_public_and_versioned(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('data.status', 'ok');
    }

    public function test_malformed_login_payload_returns_validation_error(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => ['invalid'], 'password' => 'password'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['email']]]);
    }

    public function test_password_input_cannot_be_silently_truncated_by_bcrypt(): void
    {
        foreach ([str_repeat('é', 40).'Aa1!', "ValidPass123!\0suffix"] as $password) {
            $this->postJson('/api/v1/auth/register', $this->registration([
                'password' => $password, 'password_confirmation' => $password,
            ]))->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['password']]]);
            $this->postJson('/api/v1/auth/login', ['email' => 'awa@example.test', 'password' => $password])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['password']]]);
        }
        $this->assertDatabaseCount('users', 0);
    }
}
