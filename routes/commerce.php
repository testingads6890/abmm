<?php

use App\Http\Controllers\Admin\{ManageController, CardAdminController, TicketAdminController, PartnerAdminController, AiSearchController};
use App\Http\Controllers\Api\Member\{CardController, NetworkController, BizController, PlacesController, TicketController, VendorController};
use App\Http\Controllers\Api\V1\ScanController;
use App\Http\Middleware\{AuthenticateApiToken, EnsureAdmin, EnsureMember};
use Illuminate\Support\Facades\Route;

/* Community & commerce routes (Milestone C). Loaded from routes/global.php. */
$noCsrf = [\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class];

Route::get('verify/card/{token}', [CardAdminController::class, 'verifyPage'])->middleware('throttle:60,1,verifycard');

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function () {
    // generic list / form / action screens
    Route::get('manage/{key}', [ManageController::class, 'index'])->name('manage')->where('key', '[a-z]+');
    Route::get('manage/{key}/new', [ManageController::class, 'form'])->name('manage.new')->where('key', '[a-z]+');
    Route::post('manage/{key}', [ManageController::class, 'save'])->name('manage.store')->where('key', '[a-z]+');
    Route::get('manage/{key}/{id}/edit', [ManageController::class, 'form'])->name('manage.edit')->where(['key' => '[a-z]+', 'id' => '[0-9]+']);
    Route::put('manage/{key}/{id}', [ManageController::class, 'save'])->name('manage.update')->where(['key' => '[a-z]+', 'id' => '[0-9]+']);
    Route::delete('manage/{key}/{id}', [ManageController::class, 'destroy'])->name('manage.destroy')->where(['key' => '[a-z]+', 'id' => '[0-9]+']);
    Route::post('manage/{key}/{id}/{action}', [ManageController::class, 'act'])->name('manage.act')->where(['key' => '[a-z]+', 'id' => '[0-9]+', 'action' => '[a-z_]+']);

    Route::get('partners/{kind}', [PartnerAdminController::class, 'index'])->name('partners')->where('kind', 'bureau|organiser|recruiter')->middleware('can:partners.view');
    Route::get('smart-search', [AiSearchController::class, 'index'])->name('smartsearch');
    Route::get('card', [CardAdminController::class, 'index'])->name('card')->middleware('can:community.view');
    Route::post('card/lookup', [CardAdminController::class, 'lookup'])->name('card.lookup')->middleware('can:community.view');
    Route::post('card/assign', [CardAdminController::class, 'assign'])->name('card.assign')->middleware('can:community.edit');
    Route::post('card/redeem', [CardAdminController::class, 'redeem'])->name('card.redeem')->middleware('can:community.redeem');

    Route::get('tickets', [TicketAdminController::class, 'index'])->name('tickets')->middleware('can:ticketing.view');
    Route::post('tickets/{event}/settings', [TicketAdminController::class, 'settings'])->name('tickets.settings')->middleware('can:ticketing.edit');
    Route::get('tickets/{event}/scanner', [TicketAdminController::class, 'scanner'])->name('tickets.scanner')->middleware('can:ticketing.scan');
    Route::post('tickets/{event}/scan', [TicketAdminController::class, 'scan'])->name('tickets.scan')->middleware('can:ticketing.scan');
    Route::get('tickets/{event}/attendees', [TicketAdminController::class, 'attendees'])->name('tickets.attendees')->middleware('can:ticketing.view');
    Route::get('tickets/{event}/waitlist', [TicketAdminController::class, 'waitlist'])->name('tickets.waitlist')->middleware('can:ticketing.view');
});

// public lists (no login): category tree + vendor categories
Route::prefix('api/v1/m')->withoutMiddleware($noCsrf)->middleware('throttle:60,1,pubc')->group(function () {
    Route::get('biz/categories', [BizController::class, 'categories']);
    Route::get('vendors/categories', [VendorController::class, 'categories']);
});

Route::prefix('api/v1/m')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, EnsureMember::class, 'throttle:180,1,commerce'])->group(function () {
    // member card & benefits
    Route::get('card', [CardController::class, 'card']);
    Route::get('card/categories', [CardController::class, 'categories']);
    Route::post('card/renew', [CardController::class, 'renew']);
    Route::get('benefits', [CardController::class, 'benefits']);
    Route::post('benefits/{id}/claim', [CardController::class, 'claim'])->whereNumber('id');
    Route::get('benefits/mine', [CardController::class, 'myClaims']);

    // networking
    Route::get('network/settings', [NetworkController::class, 'settings']);
    Route::put('network/settings', [NetworkController::class, 'saveSettings']);
    Route::get('network/suggestions', [NetworkController::class, 'suggestions']);
    Route::get('network/discover', [NetworkController::class, 'discover']);
    Route::get('network/members/{id}', [NetworkController::class, 'show'])->whereNumber('id');
    Route::post('network/connect', [NetworkController::class, 'connect']);
    Route::post('network/requests/{id}', [NetworkController::class, 'respond'])->whereNumber('id');
    Route::get('network/requests', [NetworkController::class, 'requests']);
    Route::get('network/connections', [NetworkController::class, 'connections']);
    Route::delete('network/connections/{id}', [NetworkController::class, 'disconnect'])->whereNumber('id');
    Route::post('network/members/{id}/block', [NetworkController::class, 'block'])->whereNumber('id');
    Route::delete('network/members/{id}/block', [NetworkController::class, 'unblock'])->whereNumber('id');
    Route::post('network/members/{id}/report', [NetworkController::class, 'report'])->whereNumber('id');
    Route::post('network/members/{id}/save', [NetworkController::class, 'save'])->whereNumber('id');
    Route::get('network/saved', [NetworkController::class, 'saved']);
    Route::get('network/history', [NetworkController::class, 'history']);
    Route::get('network/qr', [NetworkController::class, 'myQr']);
    Route::post('network/qr/scan', [NetworkController::class, 'scanQr']);
    Route::post('network/contacts', [NetworkController::class, 'contacts']);
    Route::get('chat', [NetworkController::class, 'conversations']);
    Route::get('chat/{id}', [NetworkController::class, 'messages'])->whereNumber('id');
    Route::post('chat/{id}', [NetworkController::class, 'send'])->whereNumber('id');
    Route::post('chat/messages/{mid}/report', [NetworkController::class, 'reportMessage'])->whereNumber('mid');

    // business, organisations, ads, marketplace, groups
    Route::get('biz/businesses', [BizController::class, 'businesses']);
    Route::get('biz/businesses/{id}', [BizController::class, 'business'])->whereNumber('id');
    Route::get('biz/mine', [BizController::class, 'myBusinesses']);
    Route::post('biz/businesses', [BizController::class, 'saveBusiness']);
    Route::post('biz/businesses/{id}', [BizController::class, 'saveBusiness'])->whereNumber('id');
    Route::get('ads/packages', [BizController::class, 'adPackages']);
    Route::post('ads/buy', [BizController::class, 'buyAd']);
    Route::get('ads/mine', [BizController::class, 'myAds']);
    Route::get('ads/{id}/stats', [BizController::class, 'adStats'])->whereNumber('id');
    Route::get('ads/serve', [BizController::class, 'serveAds']);
    Route::post('ads/{id}/click', [BizController::class, 'adClick'])->whereNumber('id');
    Route::get('market/products', [BizController::class, 'products']);
    Route::get('market/products/{id}', [BizController::class, 'product'])->whereNumber('id');
    Route::post('market/products', [BizController::class, 'saveProduct']);
    Route::post('market/products/{id}', [BizController::class, 'saveProduct'])->whereNumber('id');
    Route::post('market/quotes', [BizController::class, 'requestQuote']);
    Route::get('market/quotes', [BizController::class, 'myQuotes']);
    Route::post('market/quotes/{id}/accept', [BizController::class, 'buyerAccept'])->whereNumber('id');
    Route::get('market/leads', [BizController::class, 'leads']);
    Route::post('market/leads/{id}', [BizController::class, 'respondQuote'])->whereNumber('id');
    Route::get('market/analytics', [BizController::class, 'analytics']);
    Route::get('groups', [BizController::class, 'groups']);
    Route::post('groups', [BizController::class, 'createGroup']);
    Route::post('groups/{id}/join', [BizController::class, 'joinGroup'])->whereNumber('id');
    Route::post('groups/{id}/leave', [BizController::class, 'leaveGroup'])->whereNumber('id');
    Route::get('groups/{id}/members', [BizController::class, 'groupMembers'])->whereNumber('id');
    Route::get('groups/{id}/meetings', [BizController::class, 'meetings'])->whereNumber('id');
    Route::post('groups/{id}/meetings', [BizController::class, 'createMeeting'])->whereNumber('id');
    Route::post('groups/meetings/{mid}/rsvp', [BizController::class, 'meetingRsvp'])->whereNumber('mid');
    Route::get('referrals', [BizController::class, 'referrals']);
    Route::post('referrals', [BizController::class, 'giveReferral']);
    Route::post('referrals/{id}', [BizController::class, 'updateReferral'])->whereNumber('id');

    // directories: bhavan / hotel / hospital / clinic / doctor
    Route::get('places', [PlacesController::class, 'index']);
    Route::get('places/mine', [PlacesController::class, 'mineList']);
    Route::get('places/bookings', [PlacesController::class, 'myBookings']);
    Route::post('places/bookings/{id}/cancel', [PlacesController::class, 'cancelBooking'])->whereNumber('id');
    Route::post('places', [PlacesController::class, 'register']);
    Route::get('places/{id}', [PlacesController::class, 'show'])->whereNumber('id');
    Route::get('places/{id}/calendar', [PlacesController::class, 'calendar'])->whereNumber('id');
    Route::post('places/{id}/enquire', [PlacesController::class, 'enquire'])->whereNumber('id');
    Route::post('places/{id}/rooms', [PlacesController::class, 'saveRoom'])->whereNumber('id');
    Route::post('places/{id}/rooms/{room}', [PlacesController::class, 'saveRoom'])->whereNumber(['id', 'room']);
    Route::get('places/{id}/bookings', [PlacesController::class, 'ownerBookings'])->whereNumber('id');
    Route::post('places/{id}/bookings/{bid}', [PlacesController::class, 'decideBooking'])->whereNumber(['id', 'bid']);

    // tickets & online events
    Route::get('tickets/events', [TicketController::class, 'events']);
    Route::post('tickets/events/{id}/order', [TicketController::class, 'order'])->whereNumber('id');
    Route::post('tickets/events/{id}/waitlist', [TicketController::class, 'waitlist'])->whereNumber('id');
    Route::get('tickets/mine', [TicketController::class, 'myTickets']);
    Route::post('tickets/orders/{id}/cancel', [TicketController::class, 'cancel'])->whereNumber('id');
    Route::get('events/{id}/calendar.ics', [TicketController::class, 'ics'])->whereNumber('id');
    Route::get('events/{id}/join', [TicketController::class, 'joinOnline'])->whereNumber('id');
    Route::post('events/{id}/feedback', [TicketController::class, 'feedback'])->whereNumber('id');
    Route::post('events/{id}/photos', [TicketController::class, 'uploadPhoto'])->whereNumber('id');
    Route::get('events/{id}/photos', [TicketController::class, 'photos'])->whereNumber('id');

    // event vendors
    Route::get('vendors', [VendorController::class, 'index']);
    Route::get('vendors/dashboard', [VendorController::class, 'dashboard']);
    Route::get('vendors/quotes', [VendorController::class, 'myQuotes']);
    Route::post('vendors', [VendorController::class, 'register']);
    Route::get('vendors/{id}', [VendorController::class, 'show'])->whereNumber('id');
    Route::post('vendors/{id}', [VendorController::class, 'update'])->whereNumber('id');
    Route::post('vendors/{id}/quote', [VendorController::class, 'quote'])->whereNumber('id');
    Route::post('vendors/{id}/review', [VendorController::class, 'review'])->whereNumber('id');
    Route::post('vendors/quotes/{qid}/respond', [VendorController::class, 'respond'])->whereNumber('qid');
    Route::post('vendors/quotes/{qid}/accept', [VendorController::class, 'acceptQuote'])->whereNumber('qid');
});

// staff gate scanner for tickets (same bearer-token API as the RSVP scanner)
Route::prefix('api/v1')->withoutMiddleware($noCsrf)->middleware([AuthenticateApiToken::class, 'throttle:240,1,tscan', 'can:ticketing.scan'])->group(function () {
    Route::post('scan/tickets/{id}', function (\Illuminate\Http\Request $r, int $id, \App\Services\TicketService $svc) {
        $d = $r->validate(['code' => 'required|string|max:60']);
        [$st, $p] = $svc->checkIn(\App\Models\Event::findOrFail($id), $d['code'], $r->user()->id);
        return response()->json(['result' => $st] + $p, $st === 'ok' ? 200 : ($st === 'duplicate' ? 409 : 404));
    });
});
