<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CommerceRecord;
use App\Models\User;

class CommercePolicy
{
    public function view(User $user, CommerceRecord $record): bool
    {
        return $user->disabled_at === null && (int) $record->user_id === (int) $user->id;
    }

    public function cancel(User $user, CommerceRecord $record): bool
    {
        return $this->view($user, $record);
    }

    public function manage(User $user, CommerceRecord $record): bool
    {
        return $user->role === UserRole::MERCHANT && $user->disabled_at === null && $record->shop !== null && (new ShopPolicy)->update($user, $record->shop);
    }
}
