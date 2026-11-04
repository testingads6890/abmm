<?php

use App\Http\Controllers\Admin\{GeographyController, PlanController, PaymentController, FormBuilderController, VerificationController, RevenueController, MessagingController, TranslationController};
use App\Http\Controllers\Api\Member\GlobalController as MG;
use App\Http\Controllers\GlobalWebhookController;
use App\Http\Middleware\{AuthenticateApiToken, EnsureAdmin, EnsureMember};
use Illuminate\Support\Facades\Route;

/*
 * Global platform routes (Milestone A). Loaded from routes/web.php:  require __DIR__ . '/global.php';
 */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];

// ---------- gateway & WhatsApp webhooks ----------
Route::withoutMiddleware($noCsrf)->prefix('webhooks')->group(function () {
    Route::post('razorpay', [GlobalWebhookController::class, 'razorpay']);
    Route::post('stripe', [GlobalWebhookController::class, 'stripe']);
    Route::get('whatsapp', [GlobalWebhookController::class, 'whatsappVerify']);
    Route::post('whatsapp', [GlobalWebhookController::class, 'whatsapp']);
});
Route::get('pay/done', [GlobalWebhookController::class, 'done']);
Route::get('pay/cancel', [GlobalWebhookController::class, 'cancel']);

// ---------- admin panel ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('locations', [\App\Http\Controllers\Admin\LocationController::class, 'index'])->name('locations')->middleware('can:geography.view');
    Route::post('locations', [\App\Http\Controllers\Admin\LocationController::class, 'store'])->name('locations.store')->middleware('can:geography.create');
    Route::post('locations/import', [\App\Http\Controllers\Admin\LocationController::class, 'import'])->name('locations.import')->middleware('can:geography.create');
    Route::put('locations/{node}', [\App\Http\Controllers\Admin\LocationController::class, 'update'])->name('locations.update')->middleware('can:geography.edit');
    Route::post('locations/{node}/toggle', [\App\Http\Controllers\Admin\LocationController::class, 'toggle'])->name('locations.toggle')->middleware('can:geography.edit');
    Route::delete('locations/{node}', [\App\Http\Controllers\Admin\LocationController::class, 'destroy'])->name('locations.destroy')->middleware('can:geography.delete');
    Route::get('geography', [GeographyController::class, 'index'])->name('geography')->middleware('can:geography.view');
    Route::post('geography/country', [GeographyController::class, 'countrySave'])->name('geography.country')->middleware('can:geography.edit');
    Route::post('geography/country/{country}/toggle', [GeographyController::class, 'countryToggle'])->name('geography.country.toggle')->middleware('can:geography.edit');
    Route::post('geography/zones', [GeographyController::class, 'zoneStore'])->name('zones.store')->middleware('can:geography.create');
    Route::post('geography/zones/import', [GeographyController::class, 'zoneImport'])->name('zones.import')->middleware('can:geography.create');
    Route::post('geography/zones/{zone}/toggle', [GeographyController::class, 'zoneToggle'])->name('zones.toggle')->middleware('can:geography.edit');
    Route::delete('geography/zones/{zone}', [GeographyController::class, 'zoneDestroy'])->name('zones.destroy')->middleware('can:geography.delete');

    Route::get('plans', [PlanController::class, 'index'])->name('plans')->middleware('can:plans.view');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store')->middleware('can:plans.create');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update')->middleware('can:plans.edit');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy')->middleware('can:plans.delete');
    Route::post('coupons', [PlanController::class, 'couponStore'])->name('coupons.store')->middleware('can:plans.create');
    Route::post('coupons/{coupon}/toggle', [PlanController::class, 'couponToggle'])->name('coupons.toggle')->middleware('can:plans.edit');
    Route::get('subscriptions', [PlanController::class, 'subscriptions'])->name('subscriptions')->middleware('can:plans.view');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments')->middleware('can:payments.view');
    Route::post('payments/reconcile', [PaymentController::class, 'reconcile'])->name('payments.reconcile')->middleware('can:payments.reconcile');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show')->middleware('can:payments.view');
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund')->middleware('can:payments.refund');
    Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])->name('payments.markpaid')->middleware('can:payments.reconcile');
    Route::post('payments/{payment}/resend', [PaymentController::class, 'resend'])->name('payments.resend')->middleware('can:payments.view');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt')->middleware('can:payments.view');

    Route::get('forms', [FormBuilderController::class, 'index'])->name('forms.index')->middleware('can:forms.view');
    Route::get('forms/new', [FormBuilderController::class, 'edit'])->name('forms.create')->middleware('can:forms.create');
    Route::post('forms', [FormBuilderController::class, 'save'])->name('forms.store')->middleware('can:forms.create');
    Route::get('forms/submissions', [FormBuilderController::class, 'submissions'])->name('forms.submissions')->middleware('can:forms.view');
    Route::post('forms/submissions/{submission}/decide', [FormBuilderController::class, 'decide'])->name('forms.decide')->middleware('can:forms.approve');
    Route::get('forms/{form}/edit', [FormBuilderController::class, 'edit'])->name('forms.edit')->middleware('can:forms.edit');
    Route::put('forms/{form}', [FormBuilderController::class, 'save'])->name('forms.update')->middleware('can:forms.edit');
    Route::delete('forms/{form}', [FormBuilderController::class, 'destroy'])->name('forms.destroy')->middleware('can:forms.delete');

    Route::get('verification', [VerificationController::class, 'index'])->name('verification')->middleware('can:verification.view');
    Route::get('verification/{family}/proof', [VerificationController::class, 'proof'])->name('verification.proof')->middleware('can:verification.view');
    Route::post('verification/{family}/decide', [VerificationController::class, 'decide'])->name('verification.decide')->middleware('can:verification.verify');

    Route::get('revenue', [RevenueController::class, 'index'])->name('revenue')->middleware('can:revenue.view');

    Route::get('messaging', [MessagingController::class, 'index'])->name('messaging')->middleware('can:messaging.view');
    Route::post('messaging/estimate', [MessagingController::class, 'estimate'])->name('messaging.estimate')->middleware('can:messaging.view');
    Route::post('messaging/send', [MessagingController::class, 'send'])->name('messaging.send')->middleware('can:messaging.send');

    Route::get('translations', [TranslationController::class, 'index'])->name('translations')->middleware('can:settings.manage');
    Route::post('translations', [TranslationController::class, 'save'])->name('translations.save')->middleware('can:settings.manage');
});

// ---------- member app API ----------
Route::prefix('api/v1/m')->withoutMiddleware($noCsrf)->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('geo', [MG::class, 'geo']);
        Route::get('translations/{locale}', [MG::class, 'translations']);
    });
    Route::middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:120,1'])->group(function () {
        Route::post('location', [MG::class, 'setLocation']);
        Route::get('plans', [MG::class, 'plans']);
        Route::post('subscribe', [MG::class, 'subscribe'])->middleware('throttle:10,1,g-subscribe');
        Route::get('subscriptions', [MG::class, 'mySubscriptions']);
        Route::get('payments', [MG::class, 'payments']);
        Route::post('payments/verify', [MG::class, 'verifyPayment'])->middleware('throttle:20,1,g-payverify');
        Route::get('payments/{number}', [MG::class, 'paymentStatus']);
        Route::get('payments/{number}/receipt', [MG::class, 'receipt']);
        Route::post('payments/{number}/send-receipt', [MG::class, 'sendReceipt'])->middleware('throttle:5,1,g-rcpt');
        Route::get('forms', [MG::class, 'forms']);
        Route::get('forms-mine', [MG::class, 'myForms']);
        Route::post('forms/{slug}/submit', [MG::class, 'formSubmit'])->middleware('throttle:10,1,g-form');
        Route::get('verification', [MG::class, 'verification']);
        Route::post('verification/id-proof', [MG::class, 'idProof'])->middleware('throttle:10,1,g-idproof');
        Route::post('verification/references', [MG::class, 'references'])->middleware('throttle:10,1,g-refs');
        Route::get('verification/reference-requests', [MG::class, 'referenceRequests']);
        Route::post('verification/references/{id}/respond', [MG::class, 'referenceRespond'])->whereNumber('id');
        Route::post('messaging/optin', [MG::class, 'optIn']);
    });
});

require __DIR__ . '/engage.php';
require __DIR__ . '/commerce.php';
require __DIR__ . '/partners.php';
require __DIR__ . '/giving.php';
require __DIR__ . '/learning.php';
require __DIR__ . '/launch.php';
