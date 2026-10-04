<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case FULFILLED = 'fulfilled';
    case CANCELLED = 'cancelled';

    public function allows(self $next): bool
    {
        return in_array($next, match ($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::FULFILLED, self::CANCELLED],
            default => [],
        }, true);
    }
}
