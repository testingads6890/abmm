<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Event, Family};
use App\Services\Payments\PaymentLedger;
use App\Services\{PointsService, RsvpService, TicketService};
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: event tickets (paid / free / group), waitlist, QR tickets, online event links, calendar file, feedback and photos. */
class TicketController extends Controller
{
    public function __construct(private TicketService $tickets, private PaymentLedger $ledger) {}

    private function visibleEvent(Request $r, int $id): Event
    {
        $e = Event::findOrFail($id);
        abort_unless(app(RsvpService::class)->visibleTo($e, Family::withoutGlobalScopes()->findOrFail($r->user()->family_id)), 404);
        return $e;
    }

    public function events(Request $r)
    {
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); $rsvp = app(RsvpService::class);
        return Event::where('ticketing', true)->whereDate('event_date', '>=', today())->orderBy('event_date')->get()->filter(fn ($e) => $rsvp->visibleTo($e, $f))->values()->map(fn ($e) => [
            'id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'venue' => $e->venue, 'date' => $e->event_date->toDateString(), 'time' => $e->event_time, 'online' => (bool) $e->online_provider,
            'online_provider' => $e->online_provider,
            'types' => DB::table('ticket_types')->where(['event_id' => $e->id, 'is_active' => true])->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => (float) $t->price, 'currency' => $t->currency, 'left' => $this->tickets->remaining($t),
                'max_per_order' => $t->max_per_order, 'on_sale' => (! $t->sale_starts || $t->sale_starts <= now()) && (! $t->sale_ends || $t->sale_ends >= now())])]);
    }

    public function order(Request $r, int $id)
    {
        $d = $r->validate(['ticket_type_id' => 'required|integer', 'names' => 'required|array|min:1|max:20', 'names.*' => 'required|string|max:120', 'coupon' => 'nullable|string|max:30']);
        [$st, $p] = $this->tickets->order($this->visibleEvent($r, $id), $r->user(), $d['ticket_type_id'], $d['names'], $d['coupon'] ?? null);
        if ($st !== 'ok') return response()->json(['status' => $st] + $p, 422);
        $pay = $p['payment'];
        $co = $this->ledger->checkout($pay, ['title' => 'Event ticket']);
        $o = DB::table('ticket_orders')->find($p['order_id']);
        return ['order' => $o->number, 'status' => $o->status, 'amount' => (float) $pay->amount, 'currency' => $pay->currency, 'payment_no' => $pay->number, 'checkout' => $co, 'hold_minutes' => TicketService::HOLD_MINUTES];
    }

    public function myTickets(Request $r)
    {
        return DB::table('ticket_orders as o')->join('events as e', 'e.id', '=', 'o.event_id')->join('ticket_types as t', 't.id', '=', 'o.ticket_type_id')->where('o.user_id', $r->user()->id)->orderByDesc('o.id')->limit(50)
            ->get(['o.id', 'o.number', 'o.qty', 'o.amount', 'o.currency', 'o.status', 'e.id as event_id', 'e.title', 'e.event_date', 'e.venue', 't.name as type'])
            ->map(fn ($o) => (array) $o + ['tickets' => $o->status === 'paid' ? DB::table('tickets')->where('order_id', $o->id)->get(['holder_name', 'code', 'checked_in_at', 'void'])->map(fn ($t) => (array) $t + ['qr' => TicketService::qr($o->event_id, $t->code)]) : []]);
    }

    /** Free orders can be cancelled by the member; paid orders are refunded by the organiser (Payments screen). */
    public function cancel(Request $r, int $orderId)
    {
        $o = DB::table('ticket_orders')->where(['id' => $orderId, 'user_id' => $r->user()->id])->first() ?? abort(404);
        abort_unless(in_array($o->status, ['pending', 'paid'], true), 422);
        abort_if($o->status === 'paid' && $o->amount > 0, 422, 'Request a refund for a paid ticket from the organiser.');
        abort_if(DB::table('tickets')->where('order_id', $o->id)->whereNotNull('checked_in_at')->exists(), 422, 'Already checked in.');
        $this->tickets->release($o->id, 'cancelled');
        return ['message' => 'Order cancelled.'];
    }

    public function waitlist(Request $r, int $id)
    {
        $d = $r->validate(['ticket_type_id' => 'required|integer', 'qty' => 'nullable|integer|min:1|max:20']);
        $e = $this->visibleEvent($r, $id);
        $type = DB::table('ticket_types')->where(['id' => $d['ticket_type_id'], 'event_id' => $id])->first() ?? abort(404);
        abort_unless($this->tickets->remaining($type) === 0 || $this->tickets->remaining($type) < ($d['qty'] ?? 1), 422, 'Seats are available, book directly.');
        $this->tickets->joinWaitlist($e, $r->user(), $type->id, $d['qty'] ?? 1);
        return ['message' => 'Added to the waitlist. You will be notified as soon as a seat is free.'];
    }

    public function ics(Request $r, int $id)
    {
        return response(TicketService::ics($this->visibleEvent($r, $id)), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => "attachment; filename=event-$id.ics"]);
    }

    /** Online link only for people who hold a paid/free ticket or an RSVP (or if the event has no ticketing). */
    public function joinOnline(Request $r, int $id)
    {
        $e = $this->visibleEvent($r, $id);
        abort_unless($e->online_url, 404, 'This is not an online event.');
        $has = ! $e->ticketing && ! $e->rsvp_enabled
            || DB::table('ticket_orders')->where(['event_id' => $id, 'user_id' => $r->user()->id, 'status' => 'paid'])->exists()
            || DB::table('event_rsvps')->where(['event_id' => $id, 'user_id' => $r->user()->id, 'status' => 'going'])->exists();
        abort_unless($has, 403, 'Get a ticket/RSVP first.');
        DB::table('event_joins')->insert(['event_id' => $id, 'user_id' => $r->user()->id, 'joined_at' => now()]);       // online attendance
        return ['provider' => $e->online_provider, 'url' => $e->online_url, 'starts' => $e->event_date->toDateString() . ' ' . ($e->event_time ?: '')];
    }

    // ---------------- feedback & photos (after the event) ----------------
    private function attended(Request $r, Event $e): bool
    {
        $uid = $r->user()->id;
        return DB::table('event_rsvps')->where(['event_id' => $e->id, 'user_id' => $uid])->whereNotNull('checked_in_at')->exists()
            || DB::table('event_joins')->where(['event_id' => $e->id, 'user_id' => $uid])->exists()
            || DB::table('tickets as t')->join('ticket_orders as o', 'o.id', '=', 't.order_id')->where('t.event_id', $e->id)->where('o.user_id', $uid)->whereNotNull('t.checked_in_at')->exists();
    }

    public function feedback(Request $r, int $id)
    {
        $d = $r->validate(['rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:500']);
        $e = $this->visibleEvent($r, $id);
        abort_unless($e->event_date->lte(today()), 422, 'Give feedback after the event.');
        abort_unless($this->attended($r, $e), 403, 'Only attendees can give feedback.');
        DB::table('event_feedback')->updateOrInsert(['event_id' => $id, 'user_id' => $r->user()->id], $d + ['updated_at' => now(), 'created_at' => now()]);
        app(PointsService::class)->award($r->user(), 'event_feedback', (string) $id, 5);
        return ['message' => 'Dhanyavaad!'];
    }

    public function uploadPhoto(Request $r, int $id)
    {
        $r->validate(['photo' => 'required|image|max:5120', 'caption' => 'nullable|string|max:150']);
        $e = $this->visibleEvent($r, $id);
        abort_unless($e->event_date->lte(today()), 422, 'Photos are uploaded after the event.');
        abort_unless($this->attended($r, $e), 403, 'Only attendees can upload photos.');
        $pid = DB::table('event_photos')->insertGetId(['event_id' => $id, 'user_id' => $r->user()->id, 'path' => MemberApp::storePhoto($r->file('photo'), 'events'), 'caption' => $r->input('caption'), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['id' => $pid, 'message' => 'The photo will appear after moderation.'], 201);
    }

    public function photos(Request $r, int $id)
    {
        $this->visibleEvent($r, $id);
        return ['photos' => DB::table('event_photos')->where(['event_id' => $id, 'status' => 'approved'])->orderByDesc('id')->limit(100)->get(['id', 'path', 'caption'])->map(fn ($p) => ['id' => $p->id, 'url' => MemberApp::photoUrl($p->path), 'caption' => $p->caption]),
            'rating' => round((float) DB::table('event_feedback')->where('event_id', $id)->avg('rating'), 2), 'reviews' => DB::table('event_feedback')->where('event_id', $id)->count()];
    }
}
