<?php

use App\Http\Controllers\Api\Member\{PartnerController, AiController, ParichayController, MatchToolsController};
use App\Http\Middleware\{AuthenticateApiToken, EnsureMember};
use Illuminate\Support\Facades\Route;

/* Partner dashboards (Marriage Bureau / Event Organiser / Job Recruiter). Loaded from routes/global.php. */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];

Route::prefix('api/v1/m/partners')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:120,1,partners'])->group(function () {
    Route::get('kinds', [PartnerController::class, 'kinds']);
    Route::get('directory', [PartnerController::class, 'directory']);
    Route::post('register', [PartnerController::class, 'register'])->middleware('throttle:10,1,partreg');
    Route::get('mine', [PartnerController::class, 'myPartners']);
    Route::post('{id}/enquire', [PartnerController::class, 'enquire'])->whereNumber('id')->middleware('throttle:20,1,partenq');

    Route::put('{id}', [PartnerController::class, 'update'])->whereNumber('id');
    Route::get('{id}/dashboard', [PartnerController::class, 'dashboard'])->whereNumber('id');
    Route::get('{id}/records', [PartnerController::class, 'records'])->whereNumber('id');
    Route::post('{id}/records', [PartnerController::class, 'store'])->whereNumber('id');
    Route::put('{id}/records/{rid}', [PartnerController::class, 'updateRecord'])->whereNumber(['id', 'rid']);
    Route::delete('{id}/records/{rid}', [PartnerController::class, 'destroyRecord'])->whereNumber(['id', 'rid']);
    Route::get('{id}/enquiries', [PartnerController::class, 'enquiries'])->whereNumber('id');
    Route::put('{id}/enquiries/{eid}', [PartnerController::class, 'enquiryStatus'])->whereNumber(['id', 'eid']);
    Route::get('{id}/applications', [PartnerController::class, 'applications'])->whereNumber('id');
    Route::put('{id}/applications/{aid}', [PartnerController::class, 'applicationStatus'])->whereNumber(['id', 'aid']);
    Route::get('{id}/attendees', [PartnerController::class, 'attendees'])->whereNumber('id');
    Route::post('{id}/parichay', [PartnerController::class, 'parichay'])->whereNumber('id');
});

/* AI features: smart search / matches for Matrimony and Jobs, drafting helpers, keyword search across the platform. */
Route::prefix('api/v1/m')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:60,1,aisearch'])->group(function () {
    Route::get('matrimony/ai-search', [AiController::class, 'matrimonySearch']);
    Route::get('matrimony/ai-matches', [AiController::class, 'matrimonyMatches']);
    Route::get('matrimony/{id}/ai-compatibility', [AiController::class, 'compatibility'])->whereNumber('id');
    Route::post('matrimony/ai-about', [AiController::class, 'about']);
    Route::get('jobs/ai-search', [AiController::class, 'jobsSearch']);
    Route::get('jobs/ai-matches', [AiController::class, 'jobsMatches']);
    Route::post('jobs/ai-description', [AiController::class, 'jobDescription']);
    Route::get('partners/{id}/ai-rank', [AiController::class, 'rankApplicants'])->whereNumber('id');
    Route::get('search', [AiController::class, 'search']);
});

/* Milestone D: Parichay sammelan, biodata, safety and consent-based chat */
Route::prefix('api/v1/m')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:120,1,parichay'])->group(function () {
    Route::get('parichay', [ParichayController::class, 'index']);
    Route::get('parichay/{id}/participants', [ParichayController::class, 'participants'])->whereNumber('id');
    Route::post('matrimony/biodata/parse', [MatchToolsController::class, 'parse'])->middleware('throttle:10,1,biodataparse');
    Route::post('matrimony/biodata/check', [MatchToolsController::class, 'check'])->middleware('throttle:30,1,biodatacheck');
    Route::get('matrimony/biodata-prefs', [MatchToolsController::class, 'prefs']);
    Route::put('matrimony/biodata-prefs', [MatchToolsController::class, 'savePrefs']);
    Route::get('matrimony/{id}/biodata.pdf', [MatchToolsController::class, 'pdf'])->whereNumber('id')->middleware('throttle:30,1,biodatapdf');
    Route::post('matrimony/{id}/report', [MatchToolsController::class, 'report'])->whereNumber('id')->middleware('throttle:10,1,profreport');
    Route::get('matrimony/chats', [MatchToolsController::class, 'threads']);
    Route::get('matrimony/chats/{id}', [MatchToolsController::class, 'messages'])->whereNumber('id');
    Route::post('matrimony/chats/{id}', [MatchToolsController::class, 'send'])->whereNumber('id')->middleware('throttle:30,1,matchchat');
    Route::post('matrimony/chats/{id}/messages/{mid}/report', [MatchToolsController::class, 'reportMessage'])->whereNumber(['id', 'mid']);
    Route::post('matrimony/interests/{id}/consent', [MatchToolsController::class, 'consent'])->whereNumber('id');
});
