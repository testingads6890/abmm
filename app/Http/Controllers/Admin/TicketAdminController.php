<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TicketService;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Event ticketing admin: switch ticketing / online link on an event, sales summary, QR gate scanner, waitlist, attendee export. */
class TicketAdminController extends Controller
{
    public function index(TicketService $svc)
    {
        $events = Event::orderByDesc('event_date')->limit(100)->get();
        return view('admin.community.tickets', ['events' => $events, 'sales' => $events->mapWithKeys(fn ($e) => [$e->id => $svc->sales($e->id)])]);
    }

    public function settings(Request $r, Event $event)
    {
        $d = $r->validate(['online_provider' => 'nullable|in:meet,zoom,live', 'online_url' => 'nullable|url|max:300']);
        $event->update($d + ['ticketing' => $r->boolean('ticketing')]);
        $msg = 'Event settings saved.';
        if (($d['online_provider'] ?? null) === 'zoom' && empty($d['online_url'])) $msg .= app(\App\Services\ZoomService::class)->createFor($event->fresh()) ? ' Zoom meeting created and the link saved.' : ' Could not create the Zoom link (Zoom keys not set) - paste the link yourself.';
        return back()->with('ok', $msg);
    }

    public function scanner(Event $event) { return view('admin.engage.scanner', ['e' => $event, 'scanUrl' => route('admin.tickets.scan', $event), 'pattern' => 'ABMM-TK' . $event->id . '-XXXXXXXXXX']); }

    public function scan(Request $r, Event $event, TicketService $svc)
    {
        $d = $r->validate(['code' => 'required|string|max:60']);
        [$st, $p] = $svc->checkIn($event, $d['code'], $r->user()->id);
        return response()->json(['result' => $st] + $p, $st === 'ok' ? 200 : ($st === 'duplicate' ? 409 : 404));
    }

    public function attendees(Request $r, Event $event)
    {
        abort_unless(in_array($r->export, ['xlsx', 'csv', 'pdf'], true), 404);
        $rows = (function () use ($event) {
            foreach (DB::table('tickets as t')->join('ticket_orders as o', 'o.id', '=', 't.order_id')->join('users as u', 'u.id', '=', 'o.user_id')->where('t.event_id', $event->id)->orderBy('t.id')->select('t.holder_name', 't.code', 't.void', 't.checked_in_at', 'u.name', 'u.mobile', 'o.number')->cursor() as $x)
                yield [$x->holder_name, $x->code, $x->number, $x->name, $x->mobile, $x->void ? 'Void' : ($x->checked_in_at ? 'Present' : 'Absent'), $x->checked_in_at];
        })();
        return Exporter::download($r->export, 'tickets-' . $event->id, 'Tickets: ' . $event->title, ['Holder', 'Ticket code', 'Order', 'Buyer', 'Mobile', 'Attendance', 'Checked in'], $rows);
    }

    public function waitlist(Event $event)
    {
        return view('admin.community.waitlist', ['e' => $event, 'rows' => DB::table('ticket_waitlist as w')->join('users as u', 'u.id', '=', 'w.user_id')->join('ticket_types as t', 't.id', '=', 'w.ticket_type_id')->where('w.event_id', $event->id)->orderBy('w.id')->get(['u.name', 'u.mobile', 't.name as type', 'w.qty', 'w.status', 'w.created_at'])]);
    }
}
