<?php

use App\Http\Controllers\Admin\{PollController, RsvpController, PointsController, CorrectionController};
use App\Http\Controllers\Api\Member\EngageController as ME;
use App\Http\Controllers\Api\V1\ScanController;
use App\Http\Middleware\{AuthenticateApiToken, EnsureAdmin, EnsureMember};
use Illuminate\Support\Facades\Route;

/* Engagement routes (Milestone B). Loaded from routes/global.php. */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('polls', [PollController::class, 'index'])->name('polls')->middleware('can:polls.view');
    Route::get('polls/new', [PollController::class, 'edit'])->name('polls.new')->middleware('can:polls.create');
    Route::post('polls', [PollController::class, 'save'])->name('polls.store')->middleware('can:polls.create');
    Route::get('polls/{id}/edit', [PollController::class, 'edit'])->name('polls.edit')->middleware('can:polls.edit')->whereNumber('id');
    Route::put('polls/{id}', [PollController::class, 'save'])->name('polls.update')->middleware('can:polls.edit')->whereNumber('id');
    Route::delete('polls/{id}', [PollController::class, 'destroy'])->name('polls.destroy')->middleware('can:polls.delete')->whereNumber('id');
    Route::get('polls/{id}/results', [PollController::class, 'results'])->name('polls.results')->middleware('can:polls.view')->whereNumber('id');

    Route::get('rsvp', [RsvpController::class, 'index'])->name('rsvp')->middleware('can:rsvp.view');
    Route::get('rsvp/{event}', [RsvpController::class, 'show'])->name('rsvp.show')->middleware('can:rsvp.view');
    Route::post('rsvp/{event}/settings', [RsvpController::class, 'settings'])->name('rsvp.settings')->middleware('can:rsvp.edit');
    Route::get('rsvp/{event}/scanner', [RsvpController::class, 'scanner'])->name('rsvp.scanner')->middleware('can:rsvp.scan');
    Route::post('rsvp/{event}/scan', [RsvpController::class, 'scan'])->name('rsvp.scan')->middleware('can:rsvp.scan');
    Route::post('rsvp/{event}/certificates', [RsvpController::class, 'certificates'])->name('rsvp.certificates')->middleware('can:rsvp.edit');
    Route::get('rsvp/{event}/report', [RsvpController::class, 'report'])->name('rsvp.report')->middleware('can:rsvp.view');

    Route::get('points', [PointsController::class, 'index'])->name('points')->middleware('can:points.view');
    Route::post('points/rules/{id}', [PointsController::class, 'ruleSave'])->name('points.rule')->middleware('can:points.edit')->whereNumber('id');
    Route::post('points/badges', [PointsController::class, 'badgeSave'])->name('points.badge')->middleware('can:points.edit');
    Route::get('rewards', [PointsController::class, 'rewards'])->name('rewards')->middleware('can:points.view');
    Route::post('rewards', [PointsController::class, 'rewardSave'])->name('rewards.store')->middleware('can:points.edit');
    Route::post('rewards/{id}', [PointsController::class, 'rewardSave'])->name('rewards.update')->middleware('can:points.edit')->whereNumber('id');
    Route::post('redemptions/{id}', [PointsController::class, 'redemptionDecide'])->name('redemptions.decide')->middleware('can:points.redeem')->whereNumber('id');
    Route::get('certificates', [PointsController::class, 'certificates'])->name('certificates')->middleware('can:points.view');
    Route::get('certificates/{id}', [PointsController::class, 'certificateFile'])->name('certificates.file')->middleware('can:points.view')->whereNumber('id');

    Route::get('corrections', [CorrectionController::class, 'index'])->name('corrections')->middleware('can:corrections.view');
    Route::post('corrections/{id}', [CorrectionController::class, 'decide'])->name('corrections.decide')->middleware('can:corrections.decide')->whereNumber('id');
});

Route::prefix('api/v1/m')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:120,1,engage'])->group(function () {
    Route::post('ping', [ME::class, 'ping']);
    Route::get('polls', [ME::class, 'polls']);
    Route::get('polls/{id}', [ME::class, 'poll'])->whereNumber('id');
    Route::post('polls/{id}/vote', [ME::class, 'vote'])->whereNumber('id');
    Route::get('rsvp/events', [ME::class, 'events']);
    Route::post('rsvp/events/{id}', [ME::class, 'rsvp'])->whereNumber('id');
    Route::get('rsvp/passes', [ME::class, 'myPasses']);
    Route::get('points', [ME::class, 'pointsSummary']);
    Route::get('leaderboard', [ME::class, 'leaderboard']);
    Route::get('rewards', [ME::class, 'rewards']);
    Route::post('rewards/redeem', [ME::class, 'redeem']);
    Route::get('rewards/mine', [ME::class, 'myRedemptions']);
    Route::get('certificates', [ME::class, 'certificates']);
    Route::get('certificates/{id}/file', [ME::class, 'certificate'])->whereNumber('id');
    Route::get('corrections/fields', [ME::class, 'correctableFields']);
    Route::get('corrections', [ME::class, 'corrections']);
    Route::post('corrections', [ME::class, 'correctionCreate']);
    Route::post('health', [ME::class, 'health']);
});

Route::prefix('api/v1')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, 'throttle:240,1,scan', 'can:rsvp.scan'])->group(function () {
    Route::get('scan/events', [ScanController::class, 'events']);
    Route::post('scan/events/{id}', [ScanController::class, 'scan'])->whereNumber('id');
    Route::get('scan/events/{id}/summary', [ScanController::class, 'summary'])->whereNumber('id');
});
