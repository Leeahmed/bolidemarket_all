<?php

namespace App\Enums;

enum CategoryType: string
{
    case SUV = 'suv';
    case SEDAN = 'sedan';
    case CITY = 'city';
    case FOUR_BY_FOUR = 'four_by_four';
    case SPORT = 'sport';
    case LUXURY = 'luxury';
    case UTILITY = 'utility';
}
