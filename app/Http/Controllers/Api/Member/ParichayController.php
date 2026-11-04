<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\{AiService, ParichayService, RsvpService};
use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: online / offline Parichay sammelan. Registration and payment use the normal RSVP / ticket APIs (rsvp/events/{id}, tickets/events/{id}/order); this adds the list and the participant view. */
class ParichayController extends Controller
{
    public function __construct(private ParichayService $svc, private MatrimonyController $mat, private AiService $ai) {}

    public function index(Request $r, RsvpService $rsvp)
    {
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); $uid = $r->user()->id;
        return Event::where('parichay', true)->whereDate('event_date', '>=', today()->subDay())->orderBy('event_date')->get()->filter(fn ($e) => $rsvp->visibleTo($e, $f))->values()->map(function ($e) use ($uid, $r) {
            $going = DB::table('event_rsvps')->where(['event_id' => $e->id, 'user_id' => $uid, 'status' => 'going'])->exists(); $paid = DB::table('ticket_orders')->where(['event_id' => $e->id, 'user_id' => $uid, 'status' => 'paid'])->exists();
            return ['id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'venue' => $e->venue, 'date' => $e->event_date->toDateString(), 'time' => $e->event_time, 'mode' => $e->online_url || $e->online_provider ? 'online' : 'offline',
                'provider' => $e->online_provider, 'uses' => $e->ticketing ? 'tickets' : 'rsvp', 'registered' => $going || $paid, 'blocked_reason' => $this->svc->blockReason($e, $r->user()), 'attending' => $this->svc->attendingUserIds($e)->count()];
        });
    }

    /** GET parichay/{id}/participants?profile_id= : families attending (only for registered attendees), ranked by compatibility */
    public function participants(Request $r, int $id)
    {
        $e = Event::where('parichay', true)->findOrFail($id);
        $me = $this->mat->acting($r);
        abort_unless($this->svc->attendingUserIds($e)->contains($r->user()->id), 403, 'Register in this Parichay first.');
        $ranked = $this->svc->participants($e, $me)->map(fn ($p) => ['p' => $p, 'm' => $this->ai->matchScore($me, $p)])->sortByDesc(fn ($x) => $x['m']['score'])->values();
        [$short, $interest] = $this->mat->relations($me, $ranked->pluck('p.id'));
        return ['event' => $e->title, 'data' => $ranked->map(fn ($x) => $this->mat->card($x['p'], $short, $interest) + ['match' => $x['m']])->values()];
    }
}
