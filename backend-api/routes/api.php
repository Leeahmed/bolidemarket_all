<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ClientCommerceController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MerchantCommerceController;
use App\Http\Controllers\MerchantDashboardController;
use App\Http\Controllers\MerchantImageController;
use App\Http\Controllers\MerchantRegistrationController;
use App\Http\Controllers\MerchantShopController;
use App\Http\Controllers\MerchantShopMediaController;
use App\Http\Controllers\MerchantVehicleController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PriceOfferController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\VerificationController;
use App\Support\DemoMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('broadcasting/auth', fn (Request $request) => Broadcast::auth($request))->middleware(['auth:sanctum', 'active']);

foreach (['countries', 'currencies', 'cities', 'districts', 'brands', 'models', 'categories', 'features'] as $referenceType) {
    Route::get($referenceType, [ReferenceController::class, 'index'])->defaults('referenceType', $referenceType);
}
Route::get('vehicles', [CatalogController::class, 'vehicles']);
Route::get('vehicles/filters', [CatalogController::class, 'filters']);
Route::get('nearby/vehicles', [CatalogController::class, 'nearby'])->name('catalog.nearby');
Route::get('search/suggestions', [CatalogController::class, 'suggestions'])->middleware('throttle:60,1');
Route::get('vehicles/{slug}', [CatalogController::class, 'vehicle']);
Route::get('shops', [CatalogController::class, 'shops']);
Route::get('shops/{slug}', [CatalogController::class, 'shop']);
Route::get('shops/{slug}/vehicles', [CatalogController::class, 'shopVehicles']);

Route::prefix('merchant')->middleware(['auth:sanctum', 'active', 'role:merchant'])->group(function () {
    Route::get('price-offers', [PriceOfferController::class, 'merchant']);
    Route::post('price-offers/{offer}/respond', [PriceOfferController::class, 'respond']);
    Route::get('dashboard', [MerchantDashboardController::class, 'dashboard']);
    Route::get('clients', [MerchantDashboardController::class, 'clients']);
    Route::post('shops/{shop}/media', [MerchantShopMediaController::class, 'store']);
    Route::get('shops', [MerchantShopController::class, 'index']);
    Route::post('shops', [MerchantShopController::class, 'store']);
    Route::get('shops/{shop}', [MerchantShopController::class, 'show']);
    Route::put('shops/{shop}', [MerchantShopController::class, 'update']);
    Route::delete('shops/{shop}', [MerchantShopController::class, 'destroy']);
    Route::get('vehicles', [MerchantVehicleController::class, 'index']);
    Route::post('vehicles', [MerchantVehicleController::class, 'store']);
    Route::get('vehicles/{vehicle}', [MerchantVehicleController::class, 'show']);
    Route::put('vehicles/{vehicle}', [MerchantVehicleController::class, 'update']);
    Route::patch('vehicles/{vehicle}/status', [MerchantVehicleController::class, 'status']);
    Route::delete('vehicles/{vehicle}', [MerchantVehicleController::class, 'destroy']);
    Route::post('vehicles/{vehicle}/images', [MerchantImageController::class, 'store']);
    Route::delete('vehicles/{vehicle}/images/{image}', [MerchantImageController::class, 'destroy']);
    Route::patch('vehicles/{vehicle}/images/{image}/primary', [MerchantImageController::class, 'primary']);
});

Route::get('app-config', fn () => ['data' => ['demo_mode' => DemoMode::enabled(), 'default_country' => DemoMode::enabled() ? config('demo.default_country') : null]]);
Route::get('health', fn () => response()->json(['data' => ['status' => 'ok', 'service' => 'BolideMarket API']]));

Route::prefix('auth')->group(function () {
    Route::post('register-merchant', [MerchantRegistrationController::class, 'store'])->middleware('throttle:registration');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('tokens', [AuthController::class, 'token'])->middleware('throttle:login');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:password');
    Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:password');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::delete('tokens/current', [AuthController::class, 'logout']);
        Route::post('email/verification-notification', [VerificationController::class, 'send'])->middleware('throttle:6,1');
        Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    });
});

// Phase 5B.1: public availability and personal DEMO commerce.
Route::get('vehicles/{slug}/availability', [AvailabilityController::class, 'show']);
Route::middleware(['auth:sanctum', 'active', 'role:customer,merchant'])->group(function () {
    Route::patch('me/profile', [ProfileController::class, 'update']);
    Route::post('me/avatar', [ProfileController::class, 'avatar'])->middleware('throttle:20,1');
    Route::get('me/favorites', [FavoriteController::class, 'index']);
    Route::put('me/favorites/{vehicle}', [FavoriteController::class, 'store']);
    Route::delete('me/favorites/{vehicle}', [FavoriteController::class, 'destroy']);
    Route::post('rental-quotes', [ClientCommerceController::class, 'quote']);
    Route::post('reservations', [ClientCommerceController::class, 'reserve']);
    Route::post('price-offers', [PriceOfferController::class, 'store']);
    Route::get('me/price-offers/{offer}', [PriceOfferController::class, 'show']);
    Route::get('me/price-offers', [PriceOfferController::class, 'mine']);
    Route::post('orders', [ClientCommerceController::class, 'order']);
    Route::get('me/reservations', [ClientCommerceController::class, 'reservations']);
    Route::get('me/reservations/{reservation}', [ClientCommerceController::class, 'reservation']);
    Route::post('me/reservations/{reservation}/cancel', [ClientCommerceController::class, 'cancelReservation']);
    Route::get('me/orders', [ClientCommerceController::class, 'orders']);
    Route::get('me/orders/{order}', [ClientCommerceController::class, 'showOrder']);
    Route::post('me/orders/{order}/cancel', [ClientCommerceController::class, 'cancelOrder']);
});
Route::prefix('merchant')->middleware(['auth:sanctum', 'active', 'role:merchant'])->group(function () {
    Route::get('reservations', [MerchantCommerceController::class, 'reservations']);
    Route::get('reservations/{reservation}', [MerchantCommerceController::class, 'reservation']);
    Route::get('orders', [MerchantCommerceController::class, 'orders']);
    Route::get('orders/{order}', [MerchantCommerceController::class, 'order']);
    foreach (['confirm' => 'confirmed', 'reject' => 'rejected', 'start' => 'active', 'complete' => 'completed', 'cancel' => 'cancelled'] as $action => $target) {
        Route::post('reservations/{reservation}/'.$action, [MerchantCommerceController::class, 'reservationAction'])->defaults('target', $target);
    }
    foreach (['confirm' => 'confirmed', 'fulfil' => 'fulfilled', 'cancel' => 'cancelled'] as $action => $target) {
        Route::post('orders/{order}/'.$action, [MerchantCommerceController::class, 'orderAction'])->defaults('target', $target);
    }
});

// Private, read-only receipts. Source is the immutable server snapshot.
Route::prefix('me')->middleware(['auth:sanctum', 'active', 'role:customer,merchant'])->group(function () {
    Route::get('receipts', [ReceiptController::class, 'index']);
    Route::get('receipts/{reference}', [ReceiptController::class, 'show']);
    Route::get('receipts/{reference}/pdf', [ReceiptController::class, 'pdf'])->middleware('throttle:30,1');
});
Route::prefix('merchant')->middleware(['auth:sanctum', 'active', 'role:merchant'])->group(function () {
    Route::get('receipts', [ReceiptController::class, 'index']);
    Route::get('receipts/{reference}', [ReceiptController::class, 'show']);
    Route::get('receipts/{reference}/pdf', [ReceiptController::class, 'pdf'])->middleware('throttle:30,1');
});
