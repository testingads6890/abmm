<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\BloodService;
use App\Support\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Member app (Milestone E): blood donor opt-in, search, emergency requests, consent based contact, camps, hospital / ambulance directory. */
class BloodController extends Controller
{
    public function __construct(private BloodService $svc) {}

    private function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    public function meta()
    {
        return ['groups' => BloodService::GROUPS, 'compatible' => BloodService::COMPATIBLE, 'gap_days' => $this->svc->gapDays(),
            'note' => 'The donor number is visible only when the donor allows it.'];
    }

    public function me(Request $r)
    {
        $d = DB::table('blood_donors')->where('user_id', $r->user()->id)->first();
        return ['donor' => $d ? (array) $d + ['eligible' => $this->svc->isEligible($d), 'eligible_on' => $this->svc->eligibleOn($d)] : null,
            'pending_requests' => DB::table('blood_links')->where(['donor_user_id' => $r->user()->id, 'status' => 'pending'])->count()];
    }

    public function register(Request $r)
    {
        $d = $r->validate(['blood_group' => ['required', Rule::in(BloodService::GROUPS)], 'last_donated_on' => 'nullable|date|before_or_equal:today', 'available' => 'nullable|boolean', 'emergency_ok' => 'nullable|boolean',
            'city' => 'nullable|string|max:150', 'district' => 'nullable|string|max:100', 'pincode' => 'nullable|string|max:10', 'note' => 'nullable|string|max:200', 'consent' => 'accepted']);
        abort_if(DB::table('blood_donors')->where(['user_id' => $r->user()->id, 'status' => 'blocked'])->exists(), 403, 'Your donor profile is blocked.');
        unset($d['consent']);
        return response()->json(['id' => $this->svc->register($r->user(), $d), 'message' => 'You are registered as a blood donor. Thank you!'], 201);
    }

    public function optOut(Request $r)
    {
        DB::table('blood_donors')->where('user_id', $r->user()->id)->where('status', '!=', 'blocked')->delete();
        return ['ok' => true];
    }

    public function donors(Request $r)
    {
        $d = $r->validate(['group' => ['nullable', Rule::in(BloodService::GROUPS)], 'city' => 'nullable|string|max:100', 'pradesh' => 'nullable|string|max:100', 'compatible' => 'nullable|boolean', 'include_ineligible' => 'nullable|boolean']);
        return $this->svc->search($r->user(), $d['group'] ?? null, $d['city'] ?? null, $r->boolean('compatible'), ! $r->boolean('include_ineligible'), $d['pradesh'] ?? null)->values();
    }

    public function contact(Request $r, int $id)
    {
        $donor = DB::table('blood_donors')->find($id) ?? abort(404);
        $d = $r->validate(['message' => 'nullable|string|max:250']);
        return response()->json(['id' => $this->svc->requestContact($r->user(), $donor, $d['message'] ?? null), 'message' => 'Request sent to the donor. The number will be shown if they approve.'], 201);
    }

    /* ---------------------------------------------------------------- requests */

    private function reqItem(object $q, ?int $viewer = null): array
    {
        $own = $viewer === $q->user_id;
        return ['id' => $q->id, 'patient_name' => $q->patient_name, 'blood_group' => $q->blood_group, 'units' => $q->units, 'hospital' => $q->hospital, 'city' => $q->city, 'district' => $q->district, 'needed_by' => $q->needed_by,
            'urgency' => $q->urgency, 'note' => $q->note, 'status' => $q->status, 'notified' => $own ? $q->notified : null, 'created_at' => $q->created_at, 'is_mine' => $own];
    }

    public function requestStore(Request $r)
    {
        $d = $r->validate(['patient_name' => 'required|string|max:120', 'blood_group' => ['required', Rule::in(BloodService::GROUPS)], 'units' => 'nullable|integer|min:1|max:20', 'hospital' => 'required|string|max:200',
            'city' => 'nullable|string|max:150', 'district' => 'nullable|string|max:100', 'needed_by' => 'nullable|date|after_or_equal:today', 'urgency' => 'nullable|in:normal,emergency', 'contact_name' => 'nullable|string|max:100',
            'contact_phone' => ['required', 'regex:/^[0-9+\-\s]{8,18}$/'], 'note' => 'nullable|string|max:300']);
        abort_if(DB::table('blood_requests')->where('user_id', $r->user()->id)->where('status', 'open')->count() >= 3, 422, 'No more than 3 open requests at a time. Close an old request.');
        [$id, $n] = $this->svc->createRequest($r->user(), $d + ['units' => 1, 'urgency' => 'normal']);
        return response()->json(['id' => $id, 'donors_alerted' => $n, 'message' => $n ? "$n donors were alerted." : 'No eligible donor found nearby yet. Send a request yourself from donor search.'], 201);
    }

    /** Open requests in my city / state that I (as a donor) could fulfil. */
    public function requests(Request $r)
    {
        $a = Area::of($r->user());
        $donor = DB::table('blood_donors')->where('user_id', $r->user()->id)->first();
        $q = DB::table('blood_requests')->where('status', 'open')->where('user_id', '!=', $r->user()->id)->where(fn ($w) => $w->whereNull('needed_by')->orWhere('needed_by', '>=', today()->toDateString()))
            ->where(fn ($w) => $w->where('city', $a['city'])->orWhere(fn ($x) => $x->where('urgency', 'emergency')->where('pradesh', $a['pradesh'])));
        $rows = $q->orderByRaw("CASE urgency WHEN 'emergency' THEN 0 ELSE 1 END")->orderByDesc('id')->limit(50)->get();
        if ($donor) $rows = $rows->filter(fn ($x) => in_array($donor->blood_group, BloodService::COMPATIBLE[$x->blood_group] ?? [], true))->values();
        return $rows->map(fn ($x) => $this->reqItem($x, $r->user()->id))->values();
    }

    public function myRequests(Request $r)
    {
        return DB::table('blood_requests')->where('user_id', $r->user()->id)->orderByDesc('id')->get()->map(fn ($x) => $this->reqItem($x, $r->user()->id))->values();
    }

    public function requestShow(Request $r, int $id)
    {
        $q = DB::table('blood_requests')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        $links = DB::table('blood_links as l')->join('users as u', 'u.id', '=', 'l.donor_user_id')->where('l.request_id', $id)->orderByRaw("CASE l.status WHEN 'donated' THEN 0 WHEN 'approved' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
            ->get(['l.id as link_id', 'u.id as uid', 'u.name', 'u.mobile', 'l.status']);
        return $this->reqItem($q, $r->user()->id) + ['contact_phone' => $q->contact_phone, 'donors' => $links->map(fn ($l) => ['link_id' => $l->link_id, 'name' => $l->name, 'status' => $l->status,
            'blood_group' => DB::table('blood_donors')->where('user_id', $l->uid)->value('blood_group'), 'phone' => in_array($l->status, ['approved', 'donated'], true) ? $l->mobile : null])->values()];
    }

    public function requestClose(Request $r, int $id)
    {
        $q = DB::table('blood_requests')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        $d = $r->validate(['fulfilled' => 'nullable|boolean']);
        DB::table('blood_requests')->where('id', $q->id)->update(['status' => $r->boolean('fulfilled') ? 'fulfilled' : 'closed', 'updated_at' => now()]);
        DB::table('blood_links')->where(['request_id' => $q->id, 'status' => 'pending'])->update(['status' => 'declined', 'updated_at' => now()]);
        return ['ok' => true];
    }

    /* ---------------------------------------------------------------- consent links */

    public function incoming(Request $r)
    {
        return DB::table('blood_links as l')->join('users as u', 'u.id', '=', 'l.requester_user_id')->leftJoin('blood_requests as q', 'q.id', '=', 'l.request_id')->where('l.donor_user_id', $r->user()->id)->orderByDesc('l.id')->limit(50)
            ->get(['l.id', 'l.status', 'l.message', 'l.created_at', 'u.name as requester', 'q.blood_group', 'q.hospital', 'q.city', 'q.urgency', 'q.units'])->map(fn ($x) => (array) $x)->values();
    }

    public function respond(Request $r, int $id)
    {
        $l = DB::table('blood_links')->where(['id' => $id, 'donor_user_id' => $r->user()->id])->first() ?? abort(404);
        $d = $r->validate(['accept' => 'required|boolean']);
        $this->svc->respond($r->user(), $l, (bool) $d['accept']);
        return ['ok' => true];
    }

    public function donated(Request $r, int $id)
    {
        $l = DB::table('blood_links')->find($id) ?? abort(404);
        abort_unless($l->requester_user_id === $r->user()->id, 404);
        $this->svc->markDonated($r->user(), $l);
        return ['ok' => true];
    }

    /* ---------------------------------------------------------------- directory & camps */

    public function directory(Request $r)
    {
        $d = $r->validate(['kind' => 'nullable|in:hospital,ambulance,blood_bank', 'city' => 'nullable|string|max:100', 'q' => 'nullable|string|max:80']);
        return DB::table('directory_entries')->where('status', 'active')->whereIn('kind', ['hospital', 'ambulance', 'blood_bank'])->where(fn ($w) => $w->whereNull('listing_until')->orWhere('listing_until', '>=', today()->toDateString()))->when($d['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($d['city'] ?? null, fn ($q, $v) => $q->where('city', 'like', $this->like($v)))->when($d['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', $this->like($v))->orWhere('speciality', 'like', $this->like($v))))
            ->orderByDesc('verified')->orderBy('name')->limit(100)->get(['id', 'kind', 'name', 'city', 'state', 'country', 'address', 'phone', 'verified', 'description', 'member_discount_pct']);
    }

    private function drive(object $d, int $uid): array
    {
        return ['id' => $d->id, 'title' => $d->title, 'organiser' => $d->organiser, 'venue' => $d->venue, 'city' => $d->city, 'drive_date' => $d->drive_date, 'timing' => $d->timing, 'description' => $d->description,
            'contact_phone' => $d->contact_phone, 'target_units' => $d->target_units, 'collected_units' => $d->collected_units, 'status' => $d->status,
            'registered' => DB::table('blood_drive_regs')->where(['drive_id' => $d->id, 'user_id' => $uid])->exists(), 'registrations' => DB::table('blood_drive_regs')->where('drive_id', $d->id)->count()];
    }

    public function drives(Request $r)
    {
        $d = $r->validate(['city' => 'nullable|string|max:100']);
        return DB::table('blood_drives')->where('status', 'live')->whereDate('drive_date', '>=', today())->when($d['city'] ?? null, fn ($q, $v) => $q->where('city', 'like', $this->like($v)))
            ->orderBy('drive_date')->limit(100)->get()->map(fn ($x) => $this->drive($x, $r->user()->id))->values();
    }

    public function driveStore(Request $r)
    {
        $d = $r->validate(['title' => 'required|string|max:200', 'organiser' => 'nullable|string|max:150', 'venue' => 'required|string|max:200', 'drive_date' => 'required|date|after_or_equal:today', 'timing' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:2000', 'contact_phone' => ['nullable', 'regex:/^[0-9+\-\s]{8,18}$/'], 'target_units' => 'nullable|integer|min:0|max:100000', 'city' => 'nullable|string|max:150']);
        $a = Area::geo($r->user());
        $id = DB::table('blood_drives')->insertGetId(array_merge($d, $a, ['city' => $d['city'] ?? $a['city'], 'user_id' => $r->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        UserNotification::notifyAdmins('New blood donation camp for approval: ' . $d['title'], null, $a['pradesh']);
        return response()->json(['id' => $id, 'message' => 'Camp sent for review.'], 201);
    }

    public function driveRegister(Request $r, int $id)
    {
        $dr = DB::table('blood_drives')->find($id) ?? abort(404);
        return response()->json(['id' => $this->svc->driveRegister($r->user(), $dr), 'message' => 'Registered for the camp.'], 201);
    }

    public function myDrives(Request $r)
    {
        return DB::table('blood_drive_regs as g')->join('blood_drives as d', 'd.id', '=', 'g.drive_id')->where('g.user_id', $r->user()->id)->orderByDesc('g.id')->get(['d.id', 'd.title', 'd.venue', 'd.drive_date', 'd.status', 'g.attended', 'g.donated']);
    }
}
