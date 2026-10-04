<?php

namespace App\Enums;

enum FuelType: string
{
    case PETROL = 'petrol';
    case DIESEL = 'diesel';
    case HYBRID = 'hybrid';
    case ELECTRIC = 'electric';
}
