<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

class FavoritePolicy
{
    public function delete(User $user, Favorite $favorite): bool
    {
        return $user->disabled_at === null && (int) $favorite->user_id === (int) $user->id;
    }
}
