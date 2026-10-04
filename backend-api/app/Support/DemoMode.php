<?php

namespace App\Support;

final class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled') && app()->environment(['local', 'testing', 'demo']);
    }
}
