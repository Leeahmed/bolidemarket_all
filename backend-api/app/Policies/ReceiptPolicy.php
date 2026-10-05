<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\Shop;
use App\Models\User;

class ReceiptPolicy
{
    public function view(User $user, Receipt $receipt): bool
    {
        return $receipt->user_id === $user->id;
    }

    public function manage(User $user, Receipt $receipt): bool
    {
        return $user->role->value === 'merchant' && Shop::withTrashed()->managedBy($user)->whereKey($receipt->shop_id)->exists();
    }
}
