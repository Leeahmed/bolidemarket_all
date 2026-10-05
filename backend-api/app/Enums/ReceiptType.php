<?php

namespace App\Enums;

enum ReceiptType: string
{
    case SALE = 'sale';
    case RENTAL = 'rental';
}
