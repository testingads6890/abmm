<?php

use App\Http\Controllers\{AuthController, PublicController};
use App\Http\Middleware\SetLocale;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\V1 as V1;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureMember;
use App\Http\Controllers\Api\Member as M;
use App\Http\Controllers\Admin\{MasterController, TeamController, BusinessController, CommitteeController, DashboardController, DonationController, AccountController, EventController, PrintController, FamilyController, ImportController, MatrimonyController, NotificationController, ReportController, SettingController, TrustController, CustomFieldController, AppUserController, DownloadController, GreetingController, DailyMessageController, FestivalController, FeedController, HelpController, JobController, JobCandidateController, DirectoryController as AdminDirectoryController};
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Laravel 13 renamed the CSRF middleware to PreventRequestForgery (it also checks Sec-Fetch-Site). ValidateCsrfToken is a deprecated alias.

Route::middleware(SetLocale::class)->group(function () {

// ---------- Public website ----------
Route::controller(PublicController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/committee', 'committee')->name('committee');
    Route::get('/business', 'businesses')->name('businesses');
    Route::get('/events', 'events')->name('events');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'contactStore')->middleware('throttle:5,1')->name('contact.store');
    Route::get('/donate', 'donate')->name('donate');
    Route::post('/donate', 'donateStore')->middleware('throttle:5,1')->name('donate.store');
    Route::post('/donate/verify', 'donateVerify')->name('donate.verify');
    Route::post('/razorpay/webhook', 'razorpayWebhook')->name('razorpay.webhook')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    Route::get('/page/{slug}', 'page')->name('page');
});

Route::get('/login', [AuthController::class, 'show'])->middleware('guest')->name('login');
Route::get('/lang/{lang}', [AccountController::class, 'locale'])->whereIn('lang', ['en', 'hi'])->name('lang');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ---------- Admin panel ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('profile', [AccountController::class, 'profileUpdate'])->name('profile.update');
    Route::post('desktop-notify', [AccountController::class, 'desktopNotify'])->name('desktop-notify');
    Route::post('bell/read', [AccountController::class, 'readAll'])->name('bell.read');
    Route::get('feedback', [AccountController::class, 'feedback'])->name('feedback');
    Route::post('feedback', [AccountController::class, 'feedbackStore'])->name('feedback.store');
    Route::delete('feedback/{feedback}', [AccountController::class, 'feedbackDestroy'])->name('feedback.destroy');
    Route::get('app/families', [AppUserController::class, 'index'])->name('appusers.families')->middleware('can:appusers.view');
    Route::get('app/members', [AppUserController::class, 'members'])->name('appusers.members')->middleware('can:appusers.view');
    Route::get('engagement/greetings', [GreetingController::class, 'index'])->name('greetings.index')->middleware('can:engagement.view');
    Route::post('engagement/greetings/settings', [GreetingController::class, 'settings'])->name('greetings.settings')->middleware('can:engagement.edit');
    Route::post('engagement/greetings/send', [GreetingController::class, 'send'])->name('greetings.send')->middleware('can:engagement.edit');
    Route::get('help', [HelpController::class, 'index'])->name('help.index')->middleware('can:help.view');
    Route::get('help/{id}', [HelpController::class, 'show'])->name('help.show')->middleware('can:help.view')->whereNumber('id');
    Route::get('help/{id}/doc/{n}', [HelpController::class, 'doc'])->name('help.doc')->middleware('can:help.view')->whereNumber('id')->whereNumber('n');
    Route::post('help/{id}/decide', [HelpController::class, 'decide'])->name('help.decide')->middleware('can:help.edit')->whereNumber('id');
    Route::delete('help/{id}', [HelpController::class, 'destroy'])->name('help.destroy')->middleware('can:help.delete')->whereNumber('id');
    Route::get('directory', [AdminDirectoryController::class, 'index'])->name('directory.index')->middleware('can:directory.view');
    Route::post('directory/settings', [AdminDirectoryController::class, 'settings'])->name('directory.settings')->middleware('can:directory.edit');
    Route::get('jobs/candidates', [JobCandidateController::class, 'index'])->name('jobs.candidates')->middleware('can:jobs.view');
    Route::delete('jobs/candidates/{id}', [JobCandidateController::class, 'destroy'])->name('jobs.candidates.destroy')->middleware('can:jobs.delete')->whereNumber('id');
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index')->middleware('can:jobs.view');
    Route::get('jobs/{id}', [JobController::class, 'show'])->name('jobs.show')->middleware('can:jobs.view')->whereNumber('id');
    Route::post('jobs/{id}/decide', [JobController::class, 'decide'])->name('jobs.decide')->middleware('can:jobs.edit')->whereNumber('id');
    Route::delete('jobs/{id}', [JobController::class, 'destroy'])->name('jobs.destroy')->middleware('can:jobs.delete')->whereNumber('id');
    Route::get('feed', [FeedController::class, 'index'])->name('feed.index')->middleware('can:feed.view');
    Route::get('feed/create', [FeedController::class, 'create'])->name('feed.create')->middleware('can:feed.create');
    Route::post('feed', [FeedController::class, 'store'])->name('feed.store')->middleware('can:feed.create');
    Route::get('feed/toppers', [FeedController::class, 'toppers'])->name('feed.toppers')->middleware('can:feed.view');
    Route::get('feed/{id}/edit', [FeedController::class, 'edit'])->name('feed.edit')->middleware('can:feed.edit')->whereNumber('id');
    Route::put('feed/{id}', [FeedController::class, 'update'])->name('feed.update')->middleware('can:feed.edit')->whereNumber('id');
    Route::get('feed/{id}', [FeedController::class, 'show'])->name('feed.show')->middleware('can:feed.view')->whereNumber('id');
    Route::get('feed/{id}/proof', [FeedController::class, 'proof'])->name('feed.proof')->middleware('can:feed.view')->whereNumber('id');
    Route::post('feed/{id}/approve', [FeedController::class, 'approve'])->name('feed.approve')->middleware('can:feed.approve')->whereNumber('id');
    Route::post('feed/{id}/{action}', [FeedController::class, 'decide'])->name('feed.decide')->middleware('can:feed.approve')->whereNumber('id')->whereIn('action', ['changes', 'reject']);
    Route::post('feed/{id}/flag/{field}', [FeedController::class, 'flag'])->name('feed.flag')->middleware('can:feed.edit')->whereNumber('id')->whereIn('field', ['pinned', 'proof_verified']);
    Route::delete('feed/{id}', [FeedController::class, 'destroy'])->name('feed.destroy')->middleware('can:feed.delete')->whereNumber('id');
    Route::get('engagement/daily', [DailyMessageController::class, 'index'])->name('daily.index')->middleware('can:engagement.view');
    Route::post('engagement/daily', [DailyMessageController::class, 'store'])->name('daily.store')->middleware('can:engagement.create');
    Route::post('engagement/daily/bulk', [DailyMessageController::class, 'bulk'])->name('daily.bulk')->middleware('can:engagement.create');
    Route::put('engagement/daily/{id}', [DailyMessageController::class, 'update'])->name('daily.update')->middleware('can:engagement.edit')->whereNumber('id');
    Route::delete('engagement/daily/{id}', [DailyMessageController::class, 'destroy'])->name('daily.destroy')->middleware('can:engagement.delete')->whereNumber('id');
    Route::get('engagement/festivals', [FestivalController::class, 'index'])->name('festivals.index')->middleware('can:engagement.view');
    Route::post('engagement/festivals', [FestivalController::class, 'store'])->name('festivals.store')->middleware('can:engagement.create');
    Route::put('engagement/festivals/{id}', [FestivalController::class, 'update'])->name('festivals.update')->middleware('can:engagement.edit')->whereNumber('id');
    Route::delete('engagement/festivals/{id}', [FestivalController::class, 'destroy'])->name('festivals.destroy')->middleware('can:engagement.delete')->whereNumber('id');
    Route::get('app/downloads', [DownloadController::class, 'index'])->name('downloads.index')->middleware('can:downloads.view');
    Route::post('app/downloads', [DownloadController::class, 'store'])->name('downloads.store')->middleware('can:downloads.create');
    Route::put('app/downloads/{id}', [DownloadController::class, 'update'])->name('downloads.update')->middleware('can:downloads.edit')->whereNumber('id');
    Route::delete('app/downloads/{id}', [DownloadController::class, 'destroy'])->name('downloads.destroy')->middleware('can:downloads.delete')->whereNumber('id');
    Route::get('mahesh-vandana', [AccountController::class, 'vandana'])->name('vandana');
    Route::post('mahesh-vandana', [AccountController::class, 'vandanaUpload'])->name('vandana.upload');

    Route::resource('families', FamilyController::class)->only(['create', 'store'])->middleware('can:families.create');
    Route::resource('families', FamilyController::class)->only(['edit', 'update'])->middleware('can:families.edit');
    Route::resource('families', FamilyController::class)->only(['destroy'])->middleware('can:families.delete');
    Route::resource('families', FamilyController::class)->only(['index', 'show'])->middleware('can:families.view');
    Route::post('families/{family}/members', [FamilyController::class, 'addMember'])->name('families.members.store')->middleware('can:families.edit');
    Route::put('families/{family}/members/{member}', [FamilyController::class, 'updateMember'])->name('families.members.update')->middleware('can:families.edit');
    Route::post('families/{family}/members/{member}/archive', [FamilyController::class, 'archiveMember'])->name('families.members.archive')->middleware('can:families.archive');
    Route::post('families/{family}/members/{member}/restore', [FamilyController::class, 'restoreMember'])->name('families.members.restore')->middleware('can:families.archive');
    Route::delete('families/{family}/members/{member}', [FamilyController::class, 'removeMember'])->name('families.members.destroy')->middleware('can:families.delete');
    Route::post('families/{family}/toggle', [FamilyController::class, 'toggle'])->name('families.toggle')->middleware('can:families.archive');
    Route::post('members/{member}/toggle', [FamilyController::class, 'toggleMember'])->name('members.toggle')->middleware('can:families.archive');
    Route::get('print/{kind}/{id}', [PrintController::class, 'show'])->whereNumber('id')->name('print')->middleware('can:families.print');
    Route::get('families/{family}/card', [FamilyController::class, 'card'])->name('families.card')->middleware('can:families.print');

    Route::get('members/import', [\App\Http\Controllers\Admin\MemberImportController::class, 'index'])->name('members.import')->middleware('can:families.view');
    Route::get('members/import/sample', [\App\Http\Controllers\Admin\MemberImportController::class, 'sample'])->name('members.import.sample')->middleware('can:families.import');
    Route::post('members/import', [\App\Http\Controllers\Admin\MemberImportController::class, 'store'])->name('members.import.store')->middleware('can:families.import');
    Route::post('members/import/{batch}/undo', [\App\Http\Controllers\Admin\MemberImportController::class, 'undo'])->name('members.import.undo')->middleware('can:families.import');
    Route::get('members/export', [\App\Http\Controllers\Admin\MemberImportController::class, 'export'])->name('members.export')->middleware('can:families.export');
    Route::get('import', [ImportController::class, 'index'])->name('import')->middleware('can:import.run');
    Route::post('import', [ImportController::class, 'store'])->name('import.store')->middleware('can:import.run');

    Route::resource('committee', CommitteeController::class)->only(['index'])->middleware('can:committee.view');
    Route::resource('committee', CommitteeController::class)->only(['store'])->middleware('can:committee.create');
    Route::resource('committee', CommitteeController::class)->only(['update'])->middleware('can:committee.edit');
    Route::resource('committee', CommitteeController::class)->only(['destroy'])->middleware('can:committee.delete');
    Route::get('committee-sample', [CommitteeController::class, 'sample'])->name('committee.sample')->middleware('can:committee.import');
    Route::post('committee-import', [CommitteeController::class, 'import'])->name('committee.import')->middleware('can:committee.import');

    Route::get('matrimony', [MatrimonyController::class, 'index'])->name('matrimony.index')->middleware('can:matrimony.view');
    Route::get('matrimony-archive', [MatrimonyController::class, 'index'])->name('matrimony.archive')->middleware('can:matrimony.view');
    Route::post('matrimony', [MatrimonyController::class, 'store'])->name('matrimony.store')->middleware('can:matrimony.create');
    Route::get('matrimony/{profile}/edit', [MatrimonyController::class, 'edit'])->name('matrimony.edit')->middleware('can:matrimony.edit');
    Route::put('matrimony/{profile}', [MatrimonyController::class, 'update'])->name('matrimony.update')->middleware('can:matrimony.edit');
    Route::patch('matrimony/{profile}', [MatrimonyController::class, 'status'])->name('matrimony.status')->middleware('can:matrimony.status');

    Route::get('business', [BusinessController::class, 'index'])->name('business.index')->middleware('can:business.view');
    Route::post('business', [BusinessController::class, 'store'])->name('business.store')->middleware('can:business.create');
    Route::get('business/{business}/edit', [BusinessController::class, 'edit'])->name('business.edit')->middleware('can:business.edit');
    Route::put('business/{business}', [BusinessController::class, 'update'])->name('business.update')->middleware('can:business.edit');
    Route::patch('business/{business}', [BusinessController::class, 'status'])->name('business.status')->middleware('can:business.status');
    Route::get('business-reviews', [BusinessController::class, 'reviews'])->name('business.reviews')->middleware('can:business.view');
    Route::patch('business-reviews/{review}', [BusinessController::class, 'reviewStatus'])->name('business.reviews.status')->middleware('can:business.status');
    Route::get('business-ads', [BusinessController::class, 'ads'])->name('business.ads')->middleware('can:business.view');
    Route::post('business-ads', [BusinessController::class, 'adStore'])->name('business.ads.store')->middleware('can:business.ads');
    Route::patch('business-ads/{ad}', [BusinessController::class, 'adStatus'])->name('business.ads.status')->middleware('can:business.ads');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('can:notifications.view');
    Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store')->middleware('can:notifications.send');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show')->middleware('can:notifications.view');
    Route::post('notifications/{notification}/resend', [NotificationController::class, 'resend'])->name('notifications.resend')->middleware('can:notifications.send');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy')->middleware('can:notifications.delete');
    Route::get('meetings', [NotificationController::class, 'meetings'])->name('meetings')->middleware('can:notifications.view');

    Route::get('events', [EventController::class, 'index'])->name('events.index')->middleware('can:events.view');
    Route::post('events', [EventController::class, 'store'])->name('events.store')->middleware('can:events.create');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy')->middleware('can:events.delete');

    Route::get('donations', [DonationController::class, 'index'])->name('donations.index')->middleware('can:donations.view');
    Route::patch('donations/{donation}', [DonationController::class, 'status'])->name('donations.status')->middleware('can:donations.status');
    Route::get('messages', [DonationController::class, 'messages'])->name('messages')->middleware('can:donations.view');

    Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports')->middleware('can:reports.view');

    // Trusts (Super Admin): state/district-wise directory, PDF, online submission (+Razorpay fee) to a department
    Route::get('trusts', [TrustController::class, 'index'])->name('trusts.index')->middleware('can:trusts.view');
    Route::post('trusts', [TrustController::class, 'store'])->name('trusts.store')->middleware('can:trusts.create');
    Route::put('trusts/{trust}', [TrustController::class, 'update'])->name('trusts.update')->middleware('can:trusts.edit');
    Route::delete('trusts/{trust}', [TrustController::class, 'destroy'])->name('trusts.destroy')->middleware('can:trusts.delete');
    Route::get('trusts/{trust}/pdf', [TrustController::class, 'pdf'])->name('trusts.pdf')->middleware('can:trusts.export');
    Route::post('trusts/{trust}/submit', [TrustController::class, 'submit'])->name('trusts.submit')->middleware('can:trusts.submit');
    Route::post('trusts-verify', [TrustController::class, 'verify'])->name('trusts.verify')->middleware('can:trusts.submit');
    Route::get('trust-submissions', [TrustController::class, 'submissions'])->name('trusts.submissions')->middleware('can:trusts.view');
    Route::post('trust-submissions/{submission}/resend', [TrustController::class, 'resend'])->name('trusts.resend')->middleware('can:trusts.submit');
    Route::get('departments', [TrustController::class, 'departments'])->name('departments')->middleware('can:trusts.edit');
    Route::post('departments', [TrustController::class, 'departmentStore'])->name('departments.store')->middleware('can:trusts.edit');
    Route::put('departments/{department}', [TrustController::class, 'departmentUpdate'])->name('departments.update')->middleware('can:trusts.edit');
    Route::delete('departments/{department}', [TrustController::class, 'departmentDestroy'])->name('departments.destroy')->middleware('can:trusts.edit');

    // Custom form fields (Super Admin)
    Route::get('form-fields', [CustomFieldController::class, 'index'])->name('customfields')->middleware('can:customfields.view');
    Route::post('form-fields', [CustomFieldController::class, 'store'])->name('customfields.store')->middleware('can:customfields.create');
    Route::put('form-fields/{field}', [CustomFieldController::class, 'update'])->name('customfields.update')->middleware('can:customfields.edit');
    Route::delete('form-fields/{field}', [CustomFieldController::class, 'destroy'])->name('customfields.destroy')->middleware('can:customfields.delete');

    // Master Data (dropdown values)
    Route::get('masters/{type}', [MasterController::class, 'index'])->name('masters.index')->middleware('can:masters.view');
    Route::post('masters/{type}', [MasterController::class, 'store'])->name('masters.store')->middleware('can:masters.create');
    Route::put('masters/item/{lookup}', [MasterController::class, 'update'])->name('masters.update')->middleware('can:masters.edit');
    Route::delete('masters/item/{lookup}', [MasterController::class, 'destroy'])->name('masters.destroy')->middleware('can:masters.delete');

    // Team: Super Admin -> State Sub-Admin -> District Operator
    Route::get('team/create', [TeamController::class, 'create'])->name('team.create')->middleware('can:users.create');
    Route::post('team', [TeamController::class, 'store'])->name('team.store')->middleware('can:users.create');
    Route::get('team', [TeamController::class, 'index'])->name('team.index')->middleware('can:users.view');
    Route::get('team/{user}/edit', [TeamController::class, 'edit'])->name('team.edit')->middleware('can:users.edit');
    Route::put('team/{user}', [TeamController::class, 'update'])->name('team.update')->middleware('can:users.edit');
    Route::patch('team/{user}', [TeamController::class, 'toggle'])->name('team.toggle')->middleware('can:users.toggle');
    Route::post('team/{user}/reset-password', [TeamController::class, 'resetPassword'])->name('team.reset')->middleware('can:users.reset_password');

    Route::get('settings', [SettingController::class, 'index'])->name('settings')->middleware('can:settings.manage');
    Route::post('settings', [SettingController::class, 'save'])->name('settings.save')->middleware('can:settings.manage');

    Route::get('account/password', [SettingController::class, 'passwordForm'])->name('account.password');
    Route::post('settings/password', [SettingController::class, 'password'])->name('password');
    Route::post('settings/smtp', [SettingController::class, 'smtp'])->name('smtp.save')->middleware('can:settings.manage');
    Route::post('settings/offices', [SettingController::class, 'officeStore'])->name('offices.store')->middleware('can:settings.manage');
    Route::delete('settings/offices/{office}', [SettingController::class, 'officeDestroy'])->name('offices.destroy')->middleware('can:settings.manage');


});

}); // end SetLocale group

// ---------- Mobile app endpoints (shared-key; no CSRF) ----------
Route::prefix('app')->middleware('throttle:60,1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->group(function () {
    Route::post('topics', [AppController::class, 'topics']);
    Route::post('notifications/{id}/seen', [AppController::class, 'seen'])->whereNumber('id');
});

// ---------- Staff mobile API v1 (Flutter: Super Admin / State Sub-Admin / District Operator). Bearer token, no cookies/CSRF ----------
Route::prefix('api/v1')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->group(function () {
    Route::post('auth/login', [V1\AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::middleware([AuthenticateApiToken::class, 'throttle:120,1'])->group(function () {
        Route::get('me', [V1\AuthController::class, 'me']);
        Route::post('me/fcm', [V1\AuthController::class, 'fcm']);
        Route::post('auth/logout', [V1\AuthController::class, 'logout']);
        Route::get('dashboard', [V1\DashboardController::class, 'index']);
        Route::get('families', [V1\FamilyController::class, 'index'])->middleware('can:families.view');
        Route::get('families/{family}', [V1\FamilyController::class, 'show'])->middleware('can:families.view');
        Route::get('team/catalogue', [V1\TeamController::class, 'catalogue'])->middleware('can:users.view');
        Route::get('team', [V1\TeamController::class, 'index'])->middleware('can:users.view');
        Route::post('team', [V1\TeamController::class, 'store'])->middleware('can:users.create');
        Route::put('team/{user}', [V1\TeamController::class, 'update'])->middleware('can:users.edit');
        Route::patch('team/{user}/toggle', [V1\TeamController::class, 'toggle'])->middleware('can:users.toggle');
        Route::post('team/{user}/reset-password', [V1\TeamController::class, 'resetPassword'])->middleware('can:users.reset_password');
    });
});

// ---------- Member app API (Flutter normal-user app): OTP login -> Head of Family / Member. Prefix /api/v1/m ----------
Route::prefix('api/v1/m')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])->group(function () {
    // public (before login)
    Route::post('auth/verify', [M\AuthController::class, 'verify'])->middleware('throttle:20,1');
    Route::post('auth/register/head', [M\AuthController::class, 'registerHead'])->middleware('throttle:10,1');
    Route::post('auth/register/member', [M\AuthController::class, 'registerMember'])->middleware('throttle:10,1');
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('masters', [M\ContentController::class, 'masters']);
        Route::get('locations', [M\ContentController::class, 'locations']);
        Route::get('pages/{slug}', [M\ContentController::class, 'pages']);
        Route::get('contact', [M\ContentController::class, 'contact']);
    });
    // logged-in members
    Route::middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:120,1'])->group(function () {
        Route::get('me', [M\AuthController::class, 'me']);
        Route::post('me/fcm', [M\AuthController::class, 'fcm']);
        Route::post('auth/logout', [M\AuthController::class, 'logout']);
        Route::get('home', [M\FamilyController::class, 'home']);
        Route::get('profile', [M\FamilyController::class, 'profile']);
        Route::post('profile', [M\FamilyController::class, 'updateProfile']);          // POST (multipart photo)
        Route::post('family/settings', [M\FamilyController::class, 'familySettings']);
        Route::post('family/head', [M\FamilyController::class, 'changeHead']);
        Route::post('members', [M\FamilyController::class, 'store']);
        Route::get('members/removed', [M\FamilyController::class, 'removed']);
        Route::get('members/requests', [M\FamilyController::class, 'requests']);
        Route::post('members/{member}/requests/{action}', [M\FamilyController::class, 'decide'])->whereIn('action', ['approve', 'reject']);
        Route::get('members/{member}', [M\FamilyController::class, 'show']);
        Route::post('members/{member}', [M\FamilyController::class, 'update']);         // POST (multipart photo)
        Route::post('members/{member}/remove', [M\FamilyController::class, 'remove']);
        Route::post('members/{member}/restore', [M\FamilyController::class, 'restore']);
        Route::post('members/{member}/parinay', [M\ContentController::class, 'parinayRegister']);
        Route::get('parinay', [M\ContentController::class, 'parinayMine']);
        Route::post('parinay/verify', [M\ContentController::class, 'parinayVerify']);
        Route::post('parinay/{profile}/pay', [M\ContentController::class, 'parinayPay'])->middleware('throttle:10,1');
        Route::get('matrimony/meta', [M\MatrimonyController::class, 'meta']);
        Route::get('matrimony/profiles', [M\MatrimonyController::class, 'profiles']);
        Route::get('matrimony/me', [M\MatrimonyController::class, 'me']);
        Route::post('matrimony/me', [M\MatrimonyController::class, 'saveMe'])->middleware('throttle:30,1');
        Route::get('matrimony/search', [M\MatrimonyController::class, 'search'])->middleware('throttle:60,1');
        Route::get('matrimony/interests', [M\MatrimonyController::class, 'interests']);
        Route::post('matrimony/interests/{id}/respond', [M\MatrimonyController::class, 'respond'])->whereNumber('id');
        Route::get('matrimony/shortlist', [M\MatrimonyController::class, 'shortlist']);
        Route::get('matrimony/{id}', [M\MatrimonyController::class, 'show'])->whereNumber('id')->middleware('throttle:60,1');
        Route::post('matrimony/{id}/interest', [M\MatrimonyController::class, 'sendInterest'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post('matrimony/{id}/shortlist', [M\MatrimonyController::class, 'toggleShortlist'])->whereNumber('id');
        Route::get('referral', [M\ContentController::class, 'referral']);
        Route::get('help/types', [M\HelpController::class, 'types']);
        Route::get('help', [M\HelpController::class, 'index']);
        Route::post('help', [M\HelpController::class, 'store'])->middleware('throttle:10,1');
        Route::get('help/{id}', [M\HelpController::class, 'show'])->whereNumber('id');
        Route::get('directory', [M\DirectoryController::class, 'index'])->middleware('throttle:60,1');
        Route::get('directory/districts', [M\DirectoryController::class, 'districts']);
        Route::get('directory/privacy', [M\DirectoryController::class, 'privacy']);
        Route::post('directory/privacy', [M\DirectoryController::class, 'setPrivacy']);
        Route::get('jobs/meta', [M\JobController::class, 'meta']);
        Route::get('jobs/mine', [M\JobController::class, 'mine']);
        Route::get('jobs', [M\JobController::class, 'index']);
        Route::post('jobs', [M\JobController::class, 'store'])->middleware('throttle:10,1');
        Route::get('jobs/{id}', [M\JobController::class, 'show'])->whereNumber('id');
        Route::post('jobs/{id}/close', [M\JobController::class, 'close'])->whereNumber('id');
        Route::post('jobs/{id}/apply', [M\JobController::class, 'apply'])->whereNumber('id')->middleware('throttle:30,1');
        Route::get('jobs/{id}/applicants', [M\JobController::class, 'applicants'])->whereNumber('id');
        Route::get('applications/mine', [M\JobController::class, 'myApplications']);
        Route::post('applications/{id}/status', [M\JobController::class, 'setApplicationStatus'])->whereNumber('id');
        Route::get('job-profile', [M\JobController::class, 'profile']);
        Route::post('job-profile', [M\JobController::class, 'saveProfile'])->middleware('throttle:20,1');
        Route::get('candidates', [M\JobController::class, 'candidates']);
        Route::get('feed', [M\FeedController::class, 'index']);
        Route::get('feed/toppers', [M\FeedController::class, 'toppers']);
        Route::get('feed/categories', [M\FeedController::class, 'categories']);
        Route::get('feed/mine', [M\FeedController::class, 'mine']);
        Route::post('feed', [M\FeedController::class, 'store'])->middleware('throttle:10,1');
        Route::post('feed/{id}', [M\FeedController::class, 'update'])->whereNumber('id')->middleware('throttle:10,1');
        Route::delete('feed/{id}', [M\FeedController::class, 'destroy'])->whereNumber('id');
        Route::post('feed/{id}/like', [M\FeedController::class, 'like'])->whereNumber('id')->middleware('throttle:60,1');
        Route::get('daily', [M\DailyController::class, 'today']);
        Route::get('greetings/today', [M\GreetingController::class, 'today']);
        Route::post('greetings/privacy', [M\GreetingController::class, 'privacy']);
        Route::post('greetings/{member}/wish', [M\GreetingController::class, 'wish'])->whereNumber('member')->middleware('throttle:30,1');
            Route::get('vandana', [M\ContentController::class, 'vandana']);
        Route::get('downloads', [M\ContentController::class, 'downloads']);
        Route::get('patrika', [M\ContentController::class, 'patrika']);
        Route::get('trusts/form', [M\ContentController::class, 'trustForm']);
        Route::get('trusts/mine', [M\ContentController::class, 'trustMine']);
        Route::get('trusts/{id}/pdf', [M\ContentController::class, 'trustPdf'])->whereNumber('id');
        Route::post('trusts', [M\ContentController::class, 'trustApply']);
        Route::get('businesses', [M\ContentController::class, 'businesses']);
        Route::post('businesses', [M\ContentController::class, 'businessStore']);
        Route::post('donations', [M\ContentController::class, 'donate'])->middleware('throttle:10,1');
        Route::post('donations/verify', [M\ContentController::class, 'donateVerify']);
        Route::get('donations', [M\ContentController::class, 'donations']);
        Route::get('committee', [M\ContentController::class, 'committee']);
        Route::get('meetings', [M\ContentController::class, 'meetings']);
        Route::get('notifications', [M\ContentController::class, 'notifications']);
        Route::post('notifications/{id}/seen', [M\ContentController::class, 'notificationSeen'])->whereNumber('id');
        Route::get('notifications/inbox', [M\ContentController::class, 'userNotifications']);
        Route::get('topics', [M\ContentController::class, 'topics']);
    });
});

// ---------- Global platform (Milestone A): zones, plans, payments, forms, verification, messaging ----------
Route::middleware(SetLocale::class)->group(fn () => require __DIR__ . '/global.php');
