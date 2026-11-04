<?php

use App\Http\Controllers\Admin\LaunchController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

/* Milestone G: launch readiness (super admin). Loaded from routes/global.php. */
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('system/launch', [LaunchController::class, 'index'])->name('system.launch');
});

/* Member app: account deletion (Play Store / App Store requirement) + public web page that explains how to request it */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];
Route::prefix('api/v1/m/account')->withoutMiddleware($noCsrf)->middleware([\App\Http\Middleware\AuthenticateApiToken::class, \App\Http\Middleware\EnsureMember::class, 'throttle:5,1,acctdel'])->group(function () {
    Route::post('delete', [\App\Http\Controllers\Api\Member\AccountController::class, 'destroy']);
});
Route::view('delete-account', 'public.delete-account')->name('delete-account');

/* iOS: Apple In-App Purchase redeem + App Store Server Notifications */
Route::prefix('api/v1/m/iap')->withoutMiddleware($noCsrf)->middleware([\App\Http\Middleware\AuthenticateApiToken::class, \App\Http\Middleware\EnsureMember::class, 'throttle:30,1,iap'])->group(function () {
    Route::get('products', [\App\Http\Controllers\Api\Member\IapController::class, 'products']);
    Route::post('apple/redeem', [\App\Http\Controllers\Api\Member\IapController::class, 'redeem']);
});
Route::post('webhooks/apple', \App\Http\Controllers\AppleWebhookController::class)->withoutMiddleware($noCsrf)->middleware('throttle:120,1,applehook');

/* Public location feed (Country > State > District > City > Zone > Pincode) for dropdowns */
Route::prefix('api/v1/geo')->middleware('throttle:120,1')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\GeoController::class, 'children']);
    Route::get('pincode/{pin}', [\App\Http\Controllers\Api\GeoController::class, 'pincode']);
});
