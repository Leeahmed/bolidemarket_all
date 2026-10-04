<?php

namespace App\Enums;

enum ShopStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case SUSPENDED = 'suspended';
}
