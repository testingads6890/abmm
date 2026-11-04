<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\RsvpService;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Event RSVP settings, attendee list, volunteer QR scanner and the attendance report. */
class RsvpController extends Controller
{
    public function index()
    {
        $events = Event::orderByDesc('event_date')->limit(100)->get();
        $stats = DB::table('event_rsvps')->where('status', 'going')->selectRaw('event_id, COUNT(*) fam, SUM(1 + guests) heads, SUM(CASE WHEN checked_in_at IS NULL THEN 0 ELSE 1 + guests END) in_')->groupBy('event_id')->get()->keyBy('event_id');
        return view('admin.engage.rsvp', ['events' => $events, 'stats' => $stats]);
    }

    public function show(Event $event)
    {
        $rows = DB::table('event_rsvps as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.event_id', $event->id)->orderByDesc('r.id')->select('r.*', 'u.name', 'u.mobile')->paginate(50);
        return view('admin.engage.rsvp-event', ['e' => $event, 'rows' => $rows, 'sum' => app(RsvpService::class)->summary($event->id)]);
    }

    public function settings(Request $r, Event $event)
    {
        $d = $r->validate(['capacity' => 'nullable|integer|min:1', 'max_guests' => 'required|integer|min:0|max:10', 'rsvp_closes_on' => 'nullable|date', 'attend_points' => 'nullable|integer|min:0|max:1000']);
        $event->update($d + ['rsvp_enabled' => $r->boolean('rsvp_enabled')]);
        return back()->with('ok', 'RSVP settings saved.');
    }

    public function scanner(Event $event) { return view('admin.engage.scanner', ['e' => $event]); }

    public function scan(Request $r, Event $event, RsvpService $svc)
    {
        $d = $r->validate(['code' => 'required|string|max:60']);
        [$status, $payload] = $svc->checkIn($event, $d['code'], $r->user()->id);
        return response()->json(['result' => $status] + $payload, $status === 'ok' ? 200 : ($status === 'duplicate' ? 409 : 404));
    }

    public function certificates(Event $event, RsvpService $svc)
    {
        $n = $svc->issueCertificates($event);
        return back()->with('ok', "Certificates issued to $n attendees.");
    }

    public function report(Request $r, Event $event)
    {
        abort_unless(in_array($r->export, ['xlsx', 'csv', 'pdf'], true), 404);
        $rows = (function () use ($event) {
            foreach (DB::table('event_rsvps as r')->join('users as u', 'u.id', '=', 'r.user_id')->leftJoin('families as f', 'f.id', '=', 'r.family_id')->where('r.event_id', $event->id)->orderBy('r.id')
                ->select('u.name', 'u.mobile', 'f.city', 'f.zone', 'r.status', 'r.guests', 'r.checked_in_at')->cursor() as $x)
                yield [$x->name, $x->mobile, $x->city, $x->zone, $x->status, $x->guests, $x->checked_in_at ? 'Present' : 'Absent', $x->checked_in_at];
        })();
        return Exporter::download($r->export, 'attendance-' . $event->id, 'Attendance: ' . $event->title, ['Name', 'Mobile', 'City', 'Zone', 'RSVP', 'Guests', 'Attendance', 'Checked in at'], $rows);
    }
}
