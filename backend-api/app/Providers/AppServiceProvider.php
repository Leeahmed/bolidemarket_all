<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Observers\CommerceObserver;
use App\Observers\ShopObserver;
use App\Observers\VehicleImageObserver;
use App\Observers\VehicleObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require base_path('routes/channels.php');
        Vehicle::observe(VehicleObserver::class);
        VehicleImage::observe(VehicleImageObserver::class);
        Shop::observe(ShopObserver::class);
        Reservation::observe(CommerceObserver::class);
        Order::observe(CommerceObserver::class);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.$request->ip().':'.hash('sha256', mb_strtolower(
                is_string($request->input('email')) ? trim($request->input('email')) : ''
            ))),
        ]);
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.frontend_url'), '/')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));
    }
}
