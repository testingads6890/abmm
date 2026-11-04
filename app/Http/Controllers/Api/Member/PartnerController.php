<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Family, JobPost};
use App\Models\Event;
use App\Services\{PartnerService, RsvpService, TicketService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Member app: Marriage Bureau / Event Organiser / Job Recruiter dashboards (subscription based). */
class PartnerController extends Controller
{
    public function __construct(private PartnerService $svc) {}

    private function mine(Request $r, int $id): object
    {
        return DB::table('partners')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
    }

    public function kinds()
    {
        return collect(PartnerService::KINDS)->map(fn ($k, $code) => ['code' => $code, 'label' => $k['label'], 'plans' => $this->svc->plans($code)])->values();
    }

    public function register(Request $r)
    {
        $d = $r->validate(['kind' => ['required', Rule::in(array_keys(PartnerService::KINDS))], 'name' => 'required|string|max:150', 'contact_person' => 'nullable|string|max:100', 'phone' => 'required|string|max:30', 'email' => 'nullable|email|max:150',
            'pradesh' => 'nullable|string|max:100', 'district' => 'nullable|string|max:100', 'city' => 'nullable|string|max:150', 'zone' => 'nullable|string|max:100', 'description' => 'nullable|string|max:3000', 'registration_no' => 'nullable|string|max:80']);
        abort_if(DB::table('partners')->where(['user_id' => $r->user()->id, 'kind' => $d['kind']])->whereIn('status', ['pending', 'active'])->exists(), 422, 'You already have a profile in this category.');
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $id = DB::table('partners')->insertGetId(array_merge($d, ['user_id' => $r->user()->id, 'family_id' => $f->id, 'country' => $f->country, 'pradesh' => $d['pradesh'] ?? $f->pradesh ?? $f->state, 'district' => $d['district'] ?? $f->district,
            'city' => $d['city'] ?? $f->city, 'zone' => $d['zone'] ?? $f->zone, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        return response()->json(['id' => $id, 'message' => 'Submitted. You can take a subscription after approval.', 'plans' => $this->svc->plans($d['kind'])], 201);
    }

    public function myPartners(Request $r)
    {
        return DB::table('partners')->where('user_id', $r->user()->id)->orderByDesc('id')->get()->map(fn ($p) => (array) $p + ['live' => (bool) $this->svc->liveSub($p)]);
    }

    public function update(Request $r, int $id)
    {
        $this->mine($r, $id);
        $d = $r->validate(['name' => 'sometimes|string|max:150', 'contact_person' => 'nullable|string|max:100', 'phone' => 'sometimes|string|max:30', 'email' => 'nullable|email|max:150', 'city' => 'nullable|string|max:150', 'zone' => 'nullable|string|max:100', 'description' => 'nullable|string|max:3000']);
        DB::table('partners')->where('id', $id)->update($d + ['updated_at' => now()]);
        return ['message' => 'Updated.'];
    }

    public function dashboard(Request $r, int $id) { return $this->svc->dashboard($this->mine($r, $id)); }

    // ---------------- records (profiles / events / jobs) ----------------
    private function rules(string $kind, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        return match ($kind) {
            'bureau' => ['name' => "$req|string|max:150", 'gender' => "$req|in:Male,Female", 'dob' => "$req|date|before:-18 years", 'mobile' => "$req|string|max:30", 'qualification' => 'nullable|string|max:120', 'occupation' => 'nullable|string|max:120',
                'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100', 'gotra' => 'nullable|string|max:100', 'height_cm' => 'nullable|integer|min:100|max:230', 'marital_status' => 'nullable|string|max:30', 'annual_income' => 'nullable|integer|min:0',
                'diet' => 'nullable|string|max:20', 'manglik' => 'sometimes|boolean', 'about' => 'nullable|string|max:1000', 'address' => 'nullable|string|max:500'],
            'recruiter' => ['title' => "$req|string|max:180", 'company' => 'nullable|string|max:150', 'description' => "$req|string|max:5000", 'city' => 'nullable|string|max:100', 'job_type' => 'nullable|in:' . implode(',', array_keys(JobPost::TYPES)),
                'salary' => 'nullable|string|max:80', 'experience' => 'nullable|string|max:60', 'skills' => 'nullable|string|max:300', 'vacancies' => 'nullable|integer|min:1|max:1000', 'contact_name' => 'nullable|string|max:100', 'contact_phone' => "$req|string|max:30",
                'work_mode' => 'nullable|string|max:20', 'education' => 'nullable|string|max:100', 'category' => 'nullable|string|max:60'],
            default => ['title' => "$req|string|max:180", 'description' => 'nullable|string|max:3000', 'venue' => "$req|string|max:200", 'event_date' => "$req|date|after_or_equal:today", 'event_time' => 'nullable|date_format:H:i', 'capacity' => 'nullable|integer|min:1',
                'max_guests' => 'nullable|integer|min:0|max:20', 'rsvp_enabled' => 'sometimes|boolean', 'ticketing' => 'sometimes|boolean', 'online_provider' => 'nullable|in:zoom,meet,teams,other', 'online_url' => 'nullable|url|max:300',
                'city' => 'nullable|string|max:150', 'ticket_types' => 'sometimes|array|max:10', 'ticket_types.*.name' => 'required|string|max:80', 'ticket_types.*.price' => 'required|numeric|min:0', 'ticket_types.*.quantity' => 'nullable|integer|min:1'],
        };
    }

    public function records(Request $r, int $id)
    {
        $p = $this->mine($r, $id);
        $sub = $this->svc->liveSub($p);
        $rows = DB::table(PartnerService::KINDS[$p->kind]['table'])->where('partner_id', $id)->orderByDesc('id')->limit(200)->get();
        return ['locked' => ! $sub, 'quota' => $this->svc->quota($p, $sub), 'data' => $rows];
    }

    public function store(Request $r, int $id)
    {
        $p = $this->mine($r, $id);
        $sub = $this->svc->gate($p);                       // 402 + plans when locked / over quota
        $d = $r->validate($this->rules($p->kind, true));
        $end = $sub->ends_on?->toDateString();
        $now = ['created_at' => now(), 'updated_at' => now(), 'partner_id' => $p->id];
        $rid = DB::transaction(function () use ($p, $d, $end, $now, $r) {
            if ($p->kind === 'bureau') {
                do { $pid = (string) random_int(1000000000, 9999999999); } while (DB::table('matrimony_profiles')->where('parinay_id', $pid)->exists());
                return DB::table('matrimony_profiles')->insertGetId(array_merge($d, $now, ['parinay_id' => $pid, 'status' => 'paid', 'visible' => true, 'paid_until' => $end, 'registered_at' => now(), 'country' => $p->country, 'pradesh' => $p->pradesh, 'district' => $p->district,
                    'state' => $d['state'] ?? $p->pradesh, 'city' => $d['city'] ?? $p->city, 'manglik' => (bool) ($d['manglik'] ?? false)]));
            }
            if ($p->kind === 'recruiter') {
                return DB::table('jobs_board')->insertGetId(array_merge($d, $now, ['user_id' => $p->user_id, 'family_id' => $p->family_id, 'status' => 'live', 'expires_at' => $end ? "$end 23:59:59" : null, 'country' => $p->country, 'pradesh' => $p->pradesh, 'state' => $p->pradesh,
                    'district' => $p->district, 'zone' => $p->zone, 'city' => $d['city'] ?? $p->city, 'company' => $d['company'] ?? $p->name, 'job_type' => $d['job_type'] ?? 'full_time', 'vacancies' => $d['vacancies'] ?? 1]));
            }
            $tt = $d['ticket_types'] ?? []; unset($d['ticket_types']);
            $eid = DB::table('events')->insertGetId(array_merge($d, $now, ['kind' => 'event', 'audience' => 'country', 'is_public' => true, 'created_by' => $p->user_id, 'listing_until' => $end, 'country' => $p->country, 'state' => $p->pradesh, 'city' => $d['city'] ?? $p->city,
                'rsvp_enabled' => (bool) ($d['rsvp_enabled'] ?? empty($tt)), 'ticketing' => ! empty($tt) || (bool) ($d['ticketing'] ?? false)]));
            foreach ($tt as $t) DB::table('ticket_types')->insert(['event_id' => $eid, 'name' => $t['name'], 'price' => $t['price'], 'currency' => $p->country && strcasecmp($p->country, 'India') !== 0 ? (DB::table('country_settings')->where('country', $p->country)->value('currency') ?: 'INR') : 'INR',
                'quantity' => $t['quantity'] ?? null, 'max_per_order' => 10, 'is_active' => true]);
            return $eid;
        });
        if ($p->kind === 'bureau') app(\App\Services\FraudService::class)->scan(\App\Models\MatrimonyProfile::withoutGlobalScopes()->find($rid));
        return response()->json(['id' => $rid, 'quota' => $this->svc->quota($p, $sub), 'message' => ucfirst(PartnerService::KINDS[$p->kind]['noun']) . ' added.'], 201);
    }

    private function own(object $p, int $rid): object
    {
        return DB::table(PartnerService::KINDS[$p->kind]['table'])->where(['id' => $rid, 'partner_id' => $p->id])->first() ?? abort(404);
    }

    public function updateRecord(Request $r, int $id, int $rid)
    {
        $p = $this->mine($r, $id); $this->svc->gate($p, false); $this->own($p, $rid);
        $d = $r->validate(array_diff_key($this->rules($p->kind, false), ['ticket_types' => 1, 'ticket_types.*.name' => 1, 'ticket_types.*.price' => 1, 'ticket_types.*.quantity' => 1]));
        DB::table(PartnerService::KINDS[$p->kind]['table'])->where('id', $rid)->update($d + ['updated_at' => now()]);
        return ['message' => 'Updated.'];
    }

    public function destroyRecord(Request $r, int $id, int $rid)
    {
        $p = $this->mine($r, $id); $this->own($p, $rid);
        if ($p->kind === 'organiser') abort_if(DB::table('ticket_orders')->where('event_id', $rid)->where('status', 'paid')->exists(), 422, 'Tickets for this event have been sold; it cannot be deleted.');
        DB::table(PartnerService::KINDS[$p->kind]['table'])->where('id', $rid)->delete();
        return ['message' => 'Removed.'];
    }

    // ---------------- enquiries / applicants / attendees (details need a live subscription) ----------------
    public function enquiries(Request $r, int $id)
    {
        $p = $this->mine($r, $id); $sub = $this->svc->liveSub($p);
        return ['locked' => ! $sub, 'data' => DB::table('partner_enquiries')->where('partner_id', $id)->orderByDesc('id')->limit(200)->get()
            ->map(fn ($e) => $sub ? (array) $e : ['id' => $e->id, 'status' => $e->status, 'subject_type' => $e->subject_type, 'created_at' => $e->created_at, 'locked' => true])];
    }

    public function enquiryStatus(Request $r, int $id, int $eid)
    {
        $p = $this->mine($r, $id); $this->svc->gate($p, false);
        $d = $r->validate(['status' => 'required|in:new,contacted,closed']);
        abort_unless(DB::table('partner_enquiries')->where(['id' => $eid, 'partner_id' => $id])->update($d + ['updated_at' => now()]), 404);
        return ['message' => 'Updated.'];
    }

    public function applications(Request $r, int $id)
    {
        $p = $this->mine($r, $id); abort_unless($p->kind === 'recruiter', 404); $sub = $this->svc->liveSub($p);
        $q = DB::table('job_applications as a')->join('jobs_board as j', 'j.id', '=', 'a.job_id')->join('users as u', 'u.id', '=', 'a.user_id')->where('j.partner_id', $id)
            ->when($r->query('job_id'), fn ($w, $v) => $w->where('a.job_id', $v))->when($r->query('status'), fn ($w, $v) => $w->where('a.status', $v))->orderByDesc('a.id')->limit(200);
        $rows = $q->get(['a.id', 'a.job_id', 'j.title', 'a.status', 'a.note', 'a.created_at', 'u.name', 'u.mobile', 'u.email']);
        return ['locked' => ! $sub, 'data' => $rows->map(fn ($x) => $sub ? (array) $x : ['id' => $x->id, 'job_id' => $x->job_id, 'title' => $x->title, 'status' => $x->status, 'created_at' => $x->created_at, 'locked' => true])];
    }

    public function applicationStatus(Request $r, int $id, int $aid)
    {
        $p = $this->mine($r, $id); abort_unless($p->kind === 'recruiter', 404); $this->svc->gate($p, false);
        $d = $r->validate(['status' => 'required|in:' . implode(',', array_keys(JobPost::APP_STATUSES))]);
        $a = DB::table('job_applications as a')->join('jobs_board as j', 'j.id', '=', 'a.job_id')->where('a.id', $aid)->where('j.partner_id', $id)->first(['a.*', 'j.title']) ?? abort(404);
        DB::table('job_applications')->where('id', $aid)->update(['status' => $d['status'], 'updated_at' => now()]);
        \App\Models\UserNotification::create(['user_id' => $a->user_id, 'title' => "Your application ({$a->title}): " . JobPost::APP_STATUSES[$d['status']]]);
        return ['message' => 'Updated.'];
    }

    public function attendees(Request $r, int $id)
    {
        $p = $this->mine($r, $id); abort_unless($p->kind === 'organiser', 404); $this->svc->gate($p, false);
        $ev = $this->own($p, (int) $r->query('event_id'));
        return ['rsvps' => DB::table('event_rsvps as x')->join('users as u', 'u.id', '=', 'x.user_id')->where('x.event_id', $ev->id)->where('x.status', 'going')->get(['u.name', 'u.mobile', 'x.guests', 'x.checked_in_at']),
            'tickets' => DB::table('tickets as t')->join('ticket_orders as o', 'o.id', '=', 't.order_id')->where('o.event_id', $ev->id)->where('o.status', 'paid')->where('t.void', false)->get(['t.holder_name', 't.code', 't.checked_in_at'])];
    }

    /** POST partners/{id}/parichay : a marriage bureau organises a Parichay sammelan (online on Zoom / Meet, or offline) - needs a live subscription */
    public function parichay(Request $r, int $id)
    {
        $p = $this->mine($r, $id); abort_unless($p->kind === 'bureau', 404);
        $sub = $this->svc->gate($p, false);
        $d = $r->validate(['title' => 'required|string|max:180', 'description' => 'nullable|string|max:3000', 'event_date' => 'required|date|after_or_equal:today', 'event_time' => 'nullable|date_format:H:i', 'mode' => 'required|in:online,offline',
            'venue' => 'required_if:mode,offline|nullable|string|max:200', 'online_provider' => 'required_if:mode,online|nullable|in:zoom,meet,other', 'online_url' => 'nullable|url|max:300', 'capacity' => 'nullable|integer|min:2|max:5000', 'fee' => 'nullable|numeric|min:0']);
        $online = $d['mode'] === 'online';
        $eid = DB::table('events')->insertGetId(['title' => $d['title'], 'description' => $d['description'] ?? null, 'venue' => $online ? 'Online' : $d['venue'], 'event_date' => $d['event_date'], 'event_time' => $d['event_time'] ?? null, 'kind' => 'parichay', 'audience' => 'country', 'is_public' => true,
            'created_by' => $p->user_id, 'partner_id' => $p->id, 'listing_until' => $sub->ends_on?->toDateString(), 'parichay' => true, 'profile_required' => true, 'capacity' => $d['capacity'] ?? 200, 'max_guests' => 2, 'country' => $p->country, 'state' => $p->pradesh, 'city' => $p->city,
            'online_provider' => $online ? $d['online_provider'] : null, 'online_url' => $online ? ($d['online_url'] ?? null) : null, 'ticketing' => ! empty($d['fee']), 'rsvp_enabled' => empty($d['fee']), 'created_at' => now(), 'updated_at' => now()]);
        if (! empty($d['fee'])) DB::table('ticket_types')->insert(['event_id' => $eid, 'name' => 'Registration', 'price' => $d['fee'], 'currency' => 'INR', 'quantity' => $d['capacity'] ?? 200, 'max_per_order' => 4, 'is_active' => true]);
        $zoom = false;
        if ($online && $d['online_provider'] === 'zoom' && empty($d['online_url'])) $zoom = app(\App\Services\ZoomService::class)->createFor(\App\Models\Event::find($eid));
        return response()->json(['id' => $eid, 'zoom_link_created' => $zoom, 'message' => 'Parichay created.' . ($online && empty($d['online_url']) && ! $zoom ? ' Add the online link yourself.' : '')], 201);
    }

    // ---------------- directory + member enquiries ----------------
    public function directory(Request $r)
    {
        $d = $r->validate(['kind' => ['required', Rule::in(array_keys(PartnerService::KINDS))]]);
        $live = DB::table('subscriptions')->where(['subscriber_type' => $d['kind'], 'status' => 'active'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()->toDateString()))->pluck('subscriber_id');
        return DB::table('partners')->where(['kind' => $d['kind'], 'status' => 'active'])->whereIn('id', $live)->when($r->query('city'), fn ($q, $v) => $q->where('city', $v))->when($r->query('country'), fn ($q, $v) => $q->where('country', $v))
            ->when($r->query('q'), fn ($q, $v) => $q->where('name', 'like', "%$v%"))->orderByDesc('verified')->orderBy('name')->limit(100)->get(['id', 'name', 'contact_person', 'city', 'pradesh', 'country', 'description', 'verified', 'phone']);
    }

    public function enquire(Request $r, int $id)
    {
        $d = $r->validate(['message' => 'nullable|string|max:600', 'subject_type' => 'nullable|in:profile,job,event', 'subject_id' => 'nullable|integer']);
        abort_if(DB::table('partner_enquiries')->where(['partner_id' => $id, 'from_user_id' => $r->user()->id])->where('created_at', '>=', now()->subDay())->exists(), 422, 'You have just sent an enquiry.');
        $this->svc->enquire($id, $r->user(), $d['subject_type'] ?? null, $d['subject_id'] ?? null, $d['message'] ?? null);
        return response()->json(['message' => 'Enquiry sent.'], 201);
    }

    /** POST partners/{id}/scan {event_id, code} : an organiser checks people in at the gate (RSVP pass ABMM-EV.. or paid ticket ABMM-TK..). */
    public function scan(Request $r, int $id, RsvpService $rsvp, TicketService $tickets)
    {
        $p = $this->mine($r, $id); abort_unless($p->kind === 'organiser', 404); $this->svc->gate($p, false);
        $d = $r->validate(['event_id' => 'required|integer', 'code' => 'required|string|max:60']);
        $ev = Event::findOrFail($this->own($p, (int) $d['event_id'])->id);
        $isTicket = str_starts_with(strtoupper(trim($d['code'])), 'ABMM-TK');
        [$st, $payload] = $isTicket ? $tickets->checkIn($ev, $d['code'], $r->user()->id) : $rsvp->checkIn($ev, $d['code'], $r->user()->id);
        return response()->json(['result' => $st, 'kind' => $isTicket ? 'ticket' : 'rsvp'] + $payload, $st === 'ok' ? 200 : ($st === 'duplicate' ? 409 : 404));
    }

    /** GET partners/{id}/scan-summary?event_id= : how many have arrived */
    public function scanSummary(Request $r, int $id, RsvpService $rsvp, TicketService $tickets)
    {
        $p = $this->mine($r, $id); abort_unless($p->kind === 'organiser', 404);
        $ev = $this->own($p, (int) $r->query('event_id'));
        return ['rsvp' => $rsvp->summary($ev->id), 'tickets' => $tickets->sales($ev->id)];
    }
}
