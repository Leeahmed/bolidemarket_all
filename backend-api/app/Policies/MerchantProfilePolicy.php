<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\MerchantProfile;
use App\Models\User;

class MerchantProfilePolicy
{
    public function view(User $user, MerchantProfile $merchant): bool
    {
        return $user->disabled_at === null && $merchant->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, MerchantProfile $merchant): bool
    {
        return $user->disabled_at === null && $merchant->owner_user_id === $user->id
            && $merchant->members()->whereKey($user->id)->wherePivot('role', MembershipRole::OWNER->value)->exists();
    }
}
