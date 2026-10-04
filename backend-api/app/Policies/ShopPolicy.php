<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Enums\UserRole;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function create(User $user, MerchantProfile $merchant): bool
    {
        return $user->role === UserRole::MERCHANT && $user->disabled_at === null
            && $merchant->owner_user_id === $user->id
            && $merchant->members()->whereKey($user->id)->wherePivot('role', MembershipRole::OWNER->value)->exists();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->role === UserRole::MERCHANT && $user->disabled_at === null
            && $shop->merchant->members()->whereKey($user->id)->exists();
    }

    public function view(User $user, Shop $shop): bool
    {
        return $this->update($user, $shop);
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $this->create($user, $shop->merchant);
    }
}
