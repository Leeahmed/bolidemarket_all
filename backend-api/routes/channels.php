<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('merchant.{merchantId}', fn (User $user, string $merchantId) => ! $user->disabled_at && $user->role === UserRole::MERCHANT
    && $user->merchants()->whereKey($merchantId)->exists(), ['guards' => ['sanctum']]);
Broadcast::channel('user.{userId}', fn (User $user, string $userId) => ! $user->disabled_at && (string) $user->id === $userId, ['guards' => ['sanctum']]);
