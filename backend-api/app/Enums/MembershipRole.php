<?php

namespace App\Enums;

enum MembershipRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
}
