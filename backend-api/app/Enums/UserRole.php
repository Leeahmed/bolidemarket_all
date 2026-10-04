<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'customer';
    case MERCHANT = 'merchant';
    case ADMIN = 'admin';
}
