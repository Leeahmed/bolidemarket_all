<?php

namespace Database\Seeders;

use App\Actions\Auth\CreateMerchantProfile;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Les comptes de démonstration sont interdits hors local/testing.');
        }
        DB::transaction(function () {
            foreach ([UserRole::ADMIN, UserRole::CLIENT, UserRole::MERCHANT] as $role) {
                $prefix = $role === UserRole::CLIENT ? 'client' : $role->value;
                $email = $prefix.'@bolidemarket.demo';
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user = new User([
                        'first_name' => ucfirst($prefix), 'last_name' => 'Démo',
                        'email' => $email, 'phone' => '+2250700000000', 'password' => 'password',
                    ]);
                    $user->role = $role;
                    $user->email_verified_at = now();
                    $user->save();
                } elseif ($user->role !== $role) {
                    throw new RuntimeException('Compte démo existant incompatible ; aucun rôle modifié.');
                }
                $user->email_verified_at ??= now();
                $user->save();
                if ($role === UserRole::MERCHANT && ! $user->ownedMerchant()->exists()) {
                    app(CreateMerchantProfile::class)->handle($user, 'Professionnel Démo', 'BolideMarket Démo');
                }
            }
        });
    }
}
