<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function allows(self $next): bool
    {
        return in_array($next, match ($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED, self::REJECTED, self::EXPIRED],
            self::CONFIRMED => [self::ACTIVE, self::CANCELLED],
            self::ACTIVE => [self::COMPLETED],
            default => [],
        }, true);
    }
}
