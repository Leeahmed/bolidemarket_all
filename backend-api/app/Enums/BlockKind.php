<?php

namespace App\Enums;

enum BlockKind: string
{
    case HOLD = 'hold';
    case RENTAL = 'rental';
    case SALE = 'sale';
    case MAINTENANCE = 'maintenance';
}
