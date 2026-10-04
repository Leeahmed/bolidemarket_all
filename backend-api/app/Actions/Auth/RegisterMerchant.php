<?php

namespace App\Actions\Auth;

use App\Enums\MerchantApproval;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Services\CurrencyResolver;
use App\Services\PhoneNumbers;
use App\Services\ProfileImages;
use App\Support\DemoMode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RegisterMerchant
{
    public function handle(array $attributes): User
    {
        $stored = [];
        try {
            $user = DB::transaction(function () use ($attributes, &$stored) {
                $user = new User(collect($attributes)->only(['first_name', 'last_name', 'email', 'password', 'country_code'])->all());
                $user->phone = app(PhoneNumbers::class)->normalize($attributes['phone'], $attributes['country_code']);
                $user->role = UserRole::CLIENT;
                $user->save();
                $shopData = $attributes['shop'];
                $merchant = app(CreateMerchantProfile::class)->handle($user, $shopData['name'], $shopData['name']);
                if (DemoMode::enabled()) {
                    $merchant->approval_status = MerchantApproval::APPROVED;
                    $merchant->save();
                }
                $shop = new Shop;
                $shop->forceFill(collect($shopData)->except(['logo', 'cover'])->all());
                $shop->forceFill([
                    'merchant_id' => $merchant->id, 'slug' => Str::slug($shopData['name']).'-'.strtolower(Str::ulid()),
                    'phone' => $user->phone, 'email' => $user->email,
                    'currency_code' => app(CurrencyResolver::class)->forCountry($shopData['country_code']),
                    'status' => DemoMode::enabled() ? 'published' : 'draft', 'is_demo' => DemoMode::enabled(),
                ]);
                foreach (['logo', 'cover'] as $type) {
                    if (! empty($shopData[$type])) {
                        $stored[] = $key = app(ProfileImages::class)->store($shopData[$type], 'shops/onboarding');
                        $shop->{$type.'_path'} = $key;
                    }
                }
                $shop->save();

                return $user->fresh();
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($stored);
            throw $e;
        }
        if (! DemoMode::enabled()) {
            event(new Registered($user));
        }

        return $user;
    }
}
