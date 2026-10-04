<?php

namespace App\Enums;

enum DemoPaymentMethod: string
{
    case CASH = 'CASH_DEMO';
    case MOBILE_MONEY = 'MOBILE_MONEY_DEMO';
    case CARD = 'CARD_DEMO';
    case BANK_TRANSFER = 'BANK_TRANSFER_DEMO';
}
