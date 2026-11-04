<?php

use App\Http\Controllers\Api\Member\{AcademyController, ExamController, HousieController};
use App\Http\Middleware\{AuthenticateApiToken, EnsureMember};
use Illuminate\Support\Facades\Route;

/* Milestone F member API: Learning Academy, weekly MCQ exams, weekly Housie. Loaded from routes/global.php. */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];
$auth = [AuthenticateApiToken::class, EnsureMember::class];

Route::prefix('api/v1/m/academy')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:180,1,academy'])->group(function () {
    Route::get('meta', [AcademyController::class, 'meta']);
    Route::get('courses', [AcademyController::class, 'courses']);
    Route::get('courses/mine', [AcademyController::class, 'mine']);
    Route::get('courses/{id}', [AcademyController::class, 'show'])->whereNumber('id');
    Route::post('courses/{id}/enrol', [AcademyController::class, 'enrol'])->whereNumber('id')->middleware('throttle:20,1,academyenrol');
    Route::get('lessons/{id}', [AcademyController::class, 'lesson'])->whereNumber('id');
    Route::post('lessons/{id}/progress', [AcademyController::class, 'progress'])->whereNumber('id');
    Route::get('live', [AcademyController::class, 'liveClasses']);
    Route::post('live/{id}/join', [AcademyController::class, 'joinLive'])->whereNumber('id');
    Route::get('library', [AcademyController::class, 'library']);
    Route::get('library/{id}', [AcademyController::class, 'libraryOpen'])->whereNumber('id');
    Route::get('earnings', [AcademyController::class, 'earnings']);
});

Route::prefix('api/v1/m/exams')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:180,1,exams'])->group(function () {
    Route::get('profile', [ExamController::class, 'profile']);
    Route::put('profile', [ExamController::class, 'saveProfile']);
    Route::get('/', [ExamController::class, 'index']);
    Route::get('history', [ExamController::class, 'history']);
    Route::get('monthly', [ExamController::class, 'monthly']);
    Route::post('{id}/start', [ExamController::class, 'start'])->whereNumber('id')->middleware('throttle:20,1,examstart');
    Route::post('{id}/submit', [ExamController::class, 'submit'])->whereNumber('id')->middleware('throttle:20,1,examsubmit');
    Route::get('{id}/result', [ExamController::class, 'result'])->whereNumber('id');
    Route::get('{id}/leaderboard', [ExamController::class, 'leaderboard'])->whereNumber('id');
});

Route::prefix('api/v1/m/housie')->withoutMiddleware($noCsrf)->middleware([...$auth, 'throttle:300,1,housie'])->group(function () {
    Route::get('games', [HousieController::class, 'games']);
    Route::get('leaderboard', [HousieController::class, 'leaderboard']);
    Route::get('games/{id}', [HousieController::class, 'show'])->whereNumber('id');
    Route::post('games/{id}/tickets', [HousieController::class, 'buy'])->whereNumber('id')->middleware('throttle:20,1,housiebuy');
    Route::post('games/{id}/claim', [HousieController::class, 'claim'])->whereNumber('id')->middleware('throttle:30,1,housieclaim');
});

/* Admin helpers: AI course draft, bulk question import */
Route::prefix('admin')->name('admin.')->middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])->group(function () {
    Route::get('academy/ai', [\App\Http\Controllers\Admin\AcademyAdminController::class, 'ai'])->name('academy.ai');
    Route::post('academy/ai', [\App\Http\Controllers\Admin\AcademyAdminController::class, 'aiStore'])->name('academy.ai.store')->middleware('throttle:20,1,aicourse');
    Route::get('academy/questions/import', [\App\Http\Controllers\Admin\AcademyAdminController::class, 'questionImport'])->name('academy.import');
    Route::post('academy/questions/import', [\App\Http\Controllers\Admin\AcademyAdminController::class, 'questionImportStore'])->name('academy.import.store');
});
