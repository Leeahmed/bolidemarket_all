<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\MembershipRole;
use App\Enums\MerchantApproval;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RolesAndMerchantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_middleware_supports_single_and_multiple_roles(): void
    {
        foreach (UserRole::cases() as $allowed) {
            Route::get('/api/test-role/'.$allowed->value, fn () => response()->json(['ok' => true]))
                ->middleware(['api', 'auth:sanctum', 'role:'.$allowed->value]);
        }
        Route::get('/api/test-multiple', fn () => response()->json(['ok' => true]))
            ->middleware(['api', 'auth:sanctum', 'role:merchant,admin']);
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $token = $user->createToken('test')->plainTextToken;
            foreach (UserRole::cases() as $allowed) {
                $this->app['auth']->forgetGuards();
                $this->withToken($token)->getJson('/api/test-role/'.$allowed->value)
                    ->assertStatus($role === $allowed ? 200 : 403);
            }
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/test-multiple')->assertStatus($role === UserRole::CLIENT ? 403 : 200);
        }
    }

    public function test_merchant_creation_is_pending_and_owner_membership_is_consistent(): void
    {
        $owner = User::factory()->create();
        $profile = app(CreateMerchantProfile::class)->handle($owner, 'Demo Legal', 'Demo Shop');
        $this->assertSame(MerchantApproval::PENDING, $profile->approval_status);
        $this->assertSame(UserRole::MERCHANT, $owner->fresh()->role);
        $this->assertDatabaseHas('merchant_memberships', ['merchant_id' => $profile->id, 'user_id' => $owner->id, 'role' => MembershipRole::OWNER->value]);
    }

    public function test_policy_denies_cross_merchant_access_and_limits_manager_edits(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['role' => UserRole::MERCHANT]);
        $manager = User::factory()->create(['role' => UserRole::MERCHANT]);
        $profile = app(CreateMerchantProfile::class)->handle($owner, 'Demo Legal', 'Demo Shop');
        $profile->members()->attach($manager, ['role' => MembershipRole::MANAGER->value]);
        $this->assertTrue(Gate::forUser($owner)->allows('update', $profile));
        $this->assertFalse(Gate::forUser($other)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($other)->allows('update', $profile));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $profile));
    }

    public function test_duplicate_merchant_creation_is_rejected_without_duplicate_memberships(): void
    {
        $owner = User::factory()->create();
        app(CreateMerchantProfile::class)->handle($owner, 'Demo Legal', 'Demo Shop');
        try {
            app(CreateMerchantProfile::class)->handle($owner, 'Duplicate', 'Duplicate');
            $this->fail('Duplicate merchant was accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('merchant_profiles', 1);
            $this->assertDatabaseCount('merchant_memberships', 1);
        }
    }

    public function test_demo_seeder_is_idempotent_and_passwords_are_hashed(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $this->seed(DemoAccountsSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('merchant_profiles', 1);
        foreach (User::all() as $user) {
            $this->assertTrue(Hash::check('password', $user->password));
        }
    }

    public function test_demo_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        app(DemoAccountsSeeder::class)->run();
    }
}
