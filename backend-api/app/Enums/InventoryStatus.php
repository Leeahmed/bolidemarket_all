<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case AVAILABLE = 'available';
    case RENTED = 'rented';
    case SOLD = 'sold';
    case OTHER = 'other';
}
