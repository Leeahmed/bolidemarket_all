<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;

class PolishDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! DemoMode::enabled()) {
            throw new RuntimeException('Correction des comptes démo réservée au mode démo autorisé.');
        }
        $this->call(LocationSeeder::class);
        foreach (User::where('email', 'like', '%@bolidemarket.demo')->get() as $user) {
            $user->email_verified_at ??= now();
            if (! $user->country_code) {
                try {
                    $phone = PhoneNumberUtil::getInstance()->parse($user->phone);
                    $region = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($phone);
                    if (Country::whereKey($region)->exists()) {
                        $user->country_code = $region;
                    }
                } catch (\Throwable) {
                }
            }
            $user->save();
        }
    }
}
