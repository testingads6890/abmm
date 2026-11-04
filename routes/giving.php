<?php

use App\Http\Controllers\Api\Member\{CareerController, BloodController, GivingController, InvestController};
use App\Http\Middleware\{AuthenticateApiToken, EnsureMember};
use Illuminate\Support\Facades\Route;

/* Milestone E member API: careers & mentoring, blood donors, donations / 80G, investments. Loaded from routes/global.php. */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];
$auth = [AuthenticateApiToken::class, EnsureMember::class];

/* ---- careers: paid / featured jobs, alerts, drives, mentors ---- */
Route::prefix('api/v1/m/careers')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:120,1,careers'])->group(function () {
    Route::get('packs', [CareerController::class, 'packs']);
    Route::post('jobs/{id}/promote', [CareerController::class, 'promote'])->whereNumber('id')->middleware('throttle:10,1,careerpromo');
    Route::get('alerts', [CareerController::class, 'alerts']);
    Route::post('alerts', [CareerController::class, 'alertStore']);
    Route::put('alerts/{id}', [CareerController::class, 'alertUpdate'])->whereNumber('id');
    Route::delete('alerts/{id}', [CareerController::class, 'alertDestroy'])->whereNumber('id');

    Route::get('drives', [CareerController::class, 'drives']);
    Route::get('drives/mine', [CareerController::class, 'myDrives']);
    Route::post('drives', [CareerController::class, 'driveStore'])->middleware('throttle:10,1,drivenew');
    Route::get('drives/{id}', [CareerController::class, 'driveShow'])->whereNumber('id');
    Route::post('drives/{id}/register', [CareerController::class, 'driveRegister'])->whereNumber('id')->middleware('throttle:20,1,drivereg');
    Route::get('drives/{id}/registrations', [CareerController::class, 'driveRegistrations'])->whereNumber('id');
    Route::put('drives/{id}/registrations/{rid}', [CareerController::class, 'driveRegistrationStatus'])->whereNumber(['id', 'rid']);

    Route::get('mentors', [CareerController::class, 'mentors']);
    Route::get('mentors/match', [CareerController::class, 'mentorMatch']);
    Route::get('mentors/me', [CareerController::class, 'mentorMe']);
    Route::post('mentors/register', [CareerController::class, 'mentorRegister'])->middleware('throttle:10,1,mentorreg');
    Route::post('mentees/register', [CareerController::class, 'menteeRegister'])->middleware('throttle:10,1,menteereg');
    Route::post('mentors/{id}/request', [CareerController::class, 'mentorRequest'])->whereNumber('id')->middleware('throttle:10,1,mentorreq');
    Route::get('mentorships', [CareerController::class, 'mentorships']);
    Route::post('mentorships/{id}/respond', [CareerController::class, 'mentorshipRespond'])->whereNumber('id');
    Route::post('mentorships/{id}/complete', [CareerController::class, 'mentorshipComplete'])->whereNumber('id');
    Route::get('mentorships/{id}/sessions', [CareerController::class, 'sessions'])->whereNumber('id');
    Route::post('mentorships/{id}/sessions', [CareerController::class, 'sessionStore'])->whereNumber('id')->middleware('throttle:20,1,mentorsess');
    Route::put('mentorships/{id}/sessions/{sid}', [CareerController::class, 'sessionUpdate'])->whereNumber(['id', 'sid']);
});

/* ---- blood donors ---- */
Route::prefix('api/v1/m/blood')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:120,1,blood'])->group(function () {
    Route::get('meta', [BloodController::class, 'meta']);
    Route::get('me', [BloodController::class, 'me']);
    Route::post('register', [BloodController::class, 'register'])->middleware('throttle:10,1,bloodreg');
    Route::delete('me', [BloodController::class, 'optOut']);
    Route::get('donors', [BloodController::class, 'donors'])->middleware('throttle:60,1,blooddonors');
    Route::post('donors/{id}/contact', [BloodController::class, 'contact'])->whereNumber('id')->middleware('throttle:20,1,bloodcontact');
    Route::get('requests', [BloodController::class, 'requests']);
    Route::get('requests/mine', [BloodController::class, 'myRequests']);
    Route::post('requests', [BloodController::class, 'requestStore'])->middleware('throttle:6,1,bloodreq');
    Route::get('requests/{id}', [BloodController::class, 'requestShow'])->whereNumber('id');
    Route::post('requests/{id}/close', [BloodController::class, 'requestClose'])->whereNumber('id');
    Route::get('links/incoming', [BloodController::class, 'incoming']);
    Route::post('links/{id}/respond', [BloodController::class, 'respond'])->whereNumber('id');
    Route::post('links/{id}/donated', [BloodController::class, 'donated'])->whereNumber('id');
    Route::get('directory', [BloodController::class, 'directory']);
    Route::get('drives', [BloodController::class, 'drives']);
    Route::get('drives/mine', [BloodController::class, 'myDrives']);
    Route::post('drives', [BloodController::class, 'driveStore'])->middleware('throttle:10,1,bloodnew');
    Route::post('drives/{id}/register', [BloodController::class, 'driveRegister'])->whereNumber('id')->middleware('throttle:20,1,bloodreg2');
});

/* ---- donations & relief ---- */
Route::prefix('api/v1/m/giving')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:120,1,giving'])->group(function () {
    Route::get('campaigns', [GivingController::class, 'campaigns']);
    Route::get('campaigns/{id}', [GivingController::class, 'show'])->whereNumber('id');
    Route::post('campaigns/{id}/donate', [GivingController::class, 'donate'])->whereNumber('id')->middleware('throttle:15,1,givedonate');
    Route::get('donations/mine', [GivingController::class, 'myDonations']);
    Route::post('donations/{id}/pan', [GivingController::class, 'addPan'])->whereNumber('id')->middleware('throttle:10,1,givepan');
    Route::get('donations/{id}/certificate', [GivingController::class, 'certificate'])->whereNumber('id');
    Route::post('assistance', [GivingController::class, 'assistanceStore'])->middleware('throttle:6,1,giveassist');
    Route::get('assistance/mine', [GivingController::class, 'myAssistance']);
    Route::post('volunteers', [GivingController::class, 'volunteerStore'])->middleware('throttle:10,1,givevol');
    Route::get('volunteers/mine', [GivingController::class, 'myVolunteer']);
});

/* ---- investments & business growth ---- */
Route::prefix('api/v1/m/invest')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:120,1,invest'])->group(function () {
    Route::get('meta', [InvestController::class, 'meta']);
    Route::get('opportunities', [InvestController::class, 'opportunities']);
    Route::get('opportunities/{id}', [InvestController::class, 'opportunity'])->whereNumber('id');
    Route::get('profile', [InvestController::class, 'profile']);
    Route::post('profile', [InvestController::class, 'saveProfile'])->middleware('throttle:10,1,investprof');
    Route::get('investors', [InvestController::class, 'investors']);
    Route::get('showcase', [InvestController::class, 'showcase']);
    Route::get('pitches', [InvestController::class, 'pitches']);
    Route::get('pitches/mine', [InvestController::class, 'myPitches']);
    Route::post('pitches', [InvestController::class, 'pitchStore'])->middleware('throttle:6,1,pitchnew');
    Route::get('pitches/{id}', [InvestController::class, 'pitchShow'])->whereNumber('id');
    Route::post('pitches/{id}/close', [InvestController::class, 'pitchClose'])->whereNumber('id');
    Route::get('pitches/{id}/investors', [InvestController::class, 'pitchInvestors'])->whereNumber('id');
    Route::post('interest', [InvestController::class, 'interest'])->middleware('throttle:20,1,investint');
    Route::get('interests/mine', [InvestController::class, 'myInterests']);
    Route::get('webinars', [InvestController::class, 'webinars']);
});
