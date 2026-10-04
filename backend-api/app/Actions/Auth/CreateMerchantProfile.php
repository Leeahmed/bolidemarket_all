<?php

namespace App\Actions\Auth;

use App\Enums\MembershipRole;
use App\Enums\UserRole;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Foundation for later onboarding; deliberately not exposed as a public route yet.
class CreateMerchantProfile
{
    public function handle(User $owner, string $legalName, string $displayName): MerchantProfile
    {
        return DB::transaction(function () use ($owner, $legalName, $displayName) {
            $owner = User::lockForUpdate()->findOrFail($owner->id);
            if ($owner->disabled_at || $owner->role === UserRole::ADMIN || $owner->ownedMerchant()->exists()) {
                throw ValidationException::withMessages(['merchant' => ['Profil professionnel non admissible.']]);
            }
            $merchant = $owner->ownedMerchant()->create(['legal_name' => $legalName, 'display_name' => $displayName]);
            $merchant->members()->attach($owner, ['role' => MembershipRole::OWNER->value]);
            $owner->role = UserRole::MERCHANT;
            $owner->save();

            return $merchant->refresh();
        });
    }
}
