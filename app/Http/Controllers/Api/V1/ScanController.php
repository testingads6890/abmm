<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\RsvpService;
use Illuminate\Http\Request;

/** Staff / volunteer mobile API: scan a member's QR pass at the gate. Needs permission rsvp.scan. */
class ScanController extends Controller
{
    public function events()
    {
        return Event::where('rsvp_enabled', true)->whereDate('event_date', '>=', today()->subDay())->orderBy('event_date')->get(['id', 'title', 'venue', 'event_date']);
    }

    public function scan(Request $r, int $id, RsvpService $svc)
    {
        $d = $r->validate(['code' => 'required|string|max:60']);
        [$status, $payload] = $svc->checkIn(Event::findOrFail($id), $d['code'], $r->user()->id);
        return response()->json(['result' => $status] + $payload, $status === 'ok' ? 200 : ($status === 'duplicate' ? 409 : 404));
    }

    public function summary(int $id, RsvpService $svc) { return $svc->summary(Event::findOrFail($id)->id); }
}
