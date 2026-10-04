<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PhoneNumbers;
use App\Support\DemoMode;
use Illuminate\Auth\Events\Registered;

class RegisterClient
{
    public function handle(array $attributes): User
    {
        $attributes['phone'] = app(PhoneNumbers::class)->normalize($attributes['phone'], $attributes['country_code']);
        $user = new User(collect($attributes)->only(['first_name', 'last_name', 'email', 'phone', 'password', 'country_code'])->all());
        $user->role = UserRole::CLIENT;
        $user->save();
        if (! DemoMode::enabled()) {
            event(new Registered($user));
        }

        return $user;
    }
}
