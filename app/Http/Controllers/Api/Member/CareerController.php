<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{JobPost, UserNotification};
use App\Services\CareerService;
use App\Services\Payments\PaymentLedger;
use App\Support\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app (Milestone E): paid / featured jobs, job alerts, campus & internship drives, mentors / mentees, career counselling. */
class CareerController extends Controller
{
    public function __construct(private CareerService $svc, private PaymentLedger $ledger) {}

    private function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    /* ---------------------------------------------------------------- paid / featured jobs */

    public function packs()
    {
        return ['paid_posting' => $this->svc->paidPosting(), 'packs' => $this->svc->packs()->map(fn ($p) => (array) $p)->values()];
    }

    public function promote(Request $r, int $id)
    {
        $d = $r->validate(['pack_id' => 'required|integer', 'coupon' => 'nullable|string|max:40']);
        $j = JobPost::withoutGlobalScopes()->where('user_id', $r->user()->id)->find($id) ?? abort(404);
        $pack = DB::table('job_packs')->where('is_active', true)->find($d['pack_id']) ?? abort(404, 'Pack not found.');
        if ($pack->price > 0 && ($ios = \App\Support\IosPolicy::guard($r, 'jobpack'))) return $ios;   // iPhone: paid promotion is bought on the website
        abort_if($pack->kind === 'featured' && $j->status !== 'live', 422, 'Only a live job can be featured.');
        abort_if($j->status === 'rejected', 422, 'You cannot buy a pack for a rejected job.');
        abort_if($j->status === 'unpaid' && $pack->kind !== 'post', 422, 'Pay for the job post first.');
        [$oid, $p] = $this->svc->order($r->user(), $j, $pack, $d['coupon'] ?? null);
        return ['order_id' => $oid, 'payment_no' => $p->number, 'checkout' => $this->ledger->checkout($p, ['title' => $pack->name])];
    }

    /* ---------------------------------------------------------------- job alerts */

    private function alertData(Request $r): array
    {
        return $r->validate(['keywords' => 'nullable|string|max:150', 'category' => 'nullable|string|max:80', 'city' => 'nullable|string|max:100', 'job_type' => 'nullable|in:' . implode(',', array_keys(JobPost::TYPES)),
            'work_mode' => 'nullable|in:onsite,remote,hybrid', 'min_salary' => 'nullable|integer|min:0|max:10000000', 'frequency' => 'nullable|in:instant,daily', 'active' => 'nullable|boolean']);
    }

    public function alerts(Request $r) { return DB::table('job_alerts')->where('user_id', $r->user()->id)->orderByDesc('id')->get(); }

    public function alertStore(Request $r)
    {
        $d = $this->alertData($r);
        abort_if(! array_filter(array_intersect_key($d, array_flip(['keywords', 'category', 'city', 'job_type', 'work_mode', 'min_salary']))), 422, 'Provide at least one filter (keyword, category, city...).');
        abort_if(DB::table('job_alerts')->where('user_id', $r->user()->id)->count() >= 10, 422, 'Maximum 10 job alerts.');
        $id = DB::table('job_alerts')->insertGetId($d + ['frequency' => 'instant', 'active' => true, 'user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['id' => $id], 201);
    }

    public function alertUpdate(Request $r, int $id)
    {
        $a = DB::table('job_alerts')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        DB::table('job_alerts')->where('id', $a->id)->update($this->alertData($r) + ['updated_at' => now()]);
        return ['ok' => true];
    }

    public function alertDestroy(Request $r, int $id)
    {
        DB::table('job_alerts')->where(['id' => $id, 'user_id' => $r->user()->id])->delete();
        return ['ok' => true];
    }

    /* ---------------------------------------------------------------- campus / internship drives */

    private function drive(object $d, ?int $viewer = null): array
    {
        return ['id' => $d->id, 'drive_type' => $d->drive_type, 'title' => $d->title, 'institution' => $d->institution, 'company' => $d->company, 'drive_date' => $d->drive_date, 'last_date' => $d->last_date, 'positions' => $d->positions,
            'stipend' => $d->stipend, 'eligibility' => $d->eligibility, 'description' => $d->description, 'venue' => $d->venue, 'city' => $d->city, 'pradesh' => $d->pradesh, 'status' => $d->status,
            'registered' => $viewer ? DB::table('drive_registrations')->where(['drive_id' => $d->id, 'user_id' => $viewer])->exists() : false, 'registrations' => DB::table('drive_registrations')->where('drive_id', $d->id)->count()];
    }

    public function drives(Request $r)
    {
        $r->validate(['type' => 'nullable|in:campus,internship,walkin', 'city' => 'nullable|string|max:100', 'q' => 'nullable|string|max:80']);
        $rows = DB::table('placement_drives')->where('status', 'live')->where(fn ($w) => $w->whereNull('last_date')->orWhere('last_date', '>=', today()->toDateString()))
            ->when($r->query('type'), fn ($q, $v) => $q->where('drive_type', $v))->when($r->query('city'), fn ($q, $v) => $q->where('city', 'like', $this->like($v)))
            ->when($r->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', $this->like($v))->orWhere('company', 'like', $this->like($v))->orWhere('institution', 'like', $this->like($v))))
            ->orderBy('drive_date')->limit(100)->get();
        return $rows->map(fn ($d) => $this->drive($d, $r->user()->id))->values();
    }

    public function driveShow(Request $r, int $id)
    {
        $d = DB::table('placement_drives')->find($id); abort_unless($d && ($d->status === 'live' || $d->user_id === $r->user()->id), 404);
        return $this->drive($d, $r->user()->id);
    }

    public function driveStore(Request $r)
    {
        $d = $r->validate(['drive_type' => 'required|in:campus,internship,walkin', 'title' => 'required|string|max:200', 'institution' => 'nullable|string|max:200', 'company' => 'nullable|string|max:200', 'drive_date' => 'nullable|date|after_or_equal:today',
            'last_date' => 'nullable|date|after_or_equal:today', 'positions' => 'nullable|integer|min:1|max:10000', 'stipend' => 'nullable|string|max:80', 'eligibility' => 'nullable|string|max:300', 'description' => 'nullable|string|max:3000',
            'venue' => 'nullable|string|max:200', 'contact_phone' => ['nullable', 'regex:/^[0-9+\-\s]{8,18}$/'], 'city' => 'nullable|string|max:150']);
        abort_if(DB::table('placement_drives')->where('user_id', $r->user()->id)->whereIn('status', ['pending', 'live'])->count() >= 10, 422, 'No more than 10 drives at a time.');
        $a = Area::of($r->user());
        $id = DB::table('placement_drives')->insertGetId(array_merge($d, ['positions' => $d['positions'] ?? 1, 'user_id' => $r->user()->id, 'family_id' => $a['family_id'], 'country' => $a['country'], 'pradesh' => $a['pradesh'], 'district' => $a['district'],
            'city' => $d['city'] ?? $a['city'], 'zone' => $a['zone'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        UserNotification::notifyAdmins('New placement/internship drive for approval: ' . $d['title'], null, $a['pradesh']);
        return response()->json(['id' => $id, 'message' => 'Drive sent for review.'], 201);
    }

    public function driveRegister(Request $r, int $id)
    {
        $dr = DB::table('placement_drives')->find($id) ?? abort(404);
        $d = $r->validate(['college' => 'nullable|string|max:150', 'course' => 'nullable|string|max:100', 'resume_url' => 'nullable|url|max:300']);
        if (empty($d['resume_url'])) $d['resume_url'] = DB::table('job_profiles')->where('user_id', $r->user()->id)->value('resume_url');
        return response()->json(['id' => $this->svc->driveRegister($r->user(), $dr, $d), 'message' => 'Registered.'], 201);
    }

    public function myDrives(Request $r)
    {
        return ['registered' => DB::table('drive_registrations as g')->join('placement_drives as d', 'd.id', '=', 'g.drive_id')->where('g.user_id', $r->user()->id)->orderByDesc('g.id')->get(['d.id as drive_id', 'd.title', 'd.drive_date', 'd.city', 'g.status']),
            'posted' => DB::table('placement_drives')->where('user_id', $r->user()->id)->orderByDesc('id')->get()->map(fn ($d) => $this->drive($d))];
    }

    public function driveRegistrations(Request $r, int $id)
    {
        $dr = DB::table('placement_drives')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        return DB::table('drive_registrations as g')->join('users as u', 'u.id', '=', 'g.user_id')->where('g.drive_id', $dr->id)->orderBy('g.id')
            ->get(['g.id', 'u.name', 'u.mobile as phone', 'g.college', 'g.course', 'g.resume_url', 'g.status']);
    }

    public function driveRegistrationStatus(Request $r, int $id, int $rid)
    {
        $dr = DB::table('placement_drives')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        $d = $r->validate(['status' => 'required|in:shortlisted,selected,rejected']);
        $reg = DB::table('drive_registrations')->where(['id' => $rid, 'drive_id' => $dr->id])->first() ?? abort(404);
        DB::table('drive_registrations')->where('id', $reg->id)->update(['status' => $d['status'], 'updated_at' => now()]);
        UserNotification::create(['user_id' => $reg->user_id, 'title' => "{$dr->title}: your status - " . ucfirst($d['status'])]);
        return ['ok' => true];
    }

    /* ---------------------------------------------------------------- mentors & mentees */

    private function mentor(object $m, ?int $score = null, array $reasons = []): array
    {
        return ['id' => $m->id, 'name' => DB::table('users')->where('id', $m->user_id)->value('name'), 'headline' => $m->headline, 'industry' => $m->industry, 'expertise' => $m->expertise, 'experience_years' => $m->experience_years,
            'languages' => $m->languages, 'mode' => $m->mode, 'bio' => $m->bio, 'offers_counselling' => (bool) $m->offers_counselling, 'verified' => (bool) $m->verified, 'city' => $m->city, 'pradesh' => $m->pradesh,
            'slots_left' => $this->svc->mentorCapacity($m)] + ($score !== null ? ['match_score' => $score, 'match_reasons' => $reasons] : []);
    }

    public function mentors(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:80', 'industry' => 'nullable|string|max:100', 'city' => 'nullable|string|max:100', 'counselling' => 'nullable|boolean']);
        return DB::table('mentor_profiles')->where('status', 'approved')->where('user_id', '!=', $r->user()->id)
            ->when($r->boolean('counselling'), fn ($q) => $q->where('offers_counselling', true))->when($r->query('industry'), fn ($q, $v) => $q->where('industry', 'like', $this->like($v)))
            ->when($r->query('city'), fn ($q, $v) => $q->where('city', 'like', $this->like($v)))
            ->when($r->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('headline', 'like', $this->like($v))->orWhere('expertise', 'like', $this->like($v))->orWhere('industry', 'like', $this->like($v))))
            ->orderByDesc('verified')->orderByDesc('experience_years')->limit(100)->get()->map(fn ($m) => $this->mentor($m))->values();
    }

    public function mentorMatch(Request $r)
    {
        $d = $r->validate(['topic' => 'nullable|string|max:200', 'counselling' => 'nullable|boolean']);
        return collect($this->svc->matchMentors($r->user(), $d['topic'] ?? null, $r->boolean('counselling')))->map(fn ($x) => $this->mentor($x['mentor'], $x['score'], $x['reasons']))->values();
    }

    public function mentorRegister(Request $r)
    {
        $d = $r->validate(['headline' => 'required|string|max:160', 'industry' => 'nullable|string|max:100', 'expertise' => 'required|string|max:300', 'experience_years' => 'nullable|integer|min:0|max:60', 'languages' => 'nullable|string|max:120',
            'mode' => 'nullable|in:online,offline,both', 'bio' => 'nullable|string|max:2000', 'linkedin' => 'nullable|url|max:200', 'max_mentees' => 'nullable|integer|min:1|max:20', 'offers_counselling' => 'nullable|boolean']);
        $ex = DB::table('mentor_profiles')->where('user_id', $r->user()->id)->first();
        if ($ex) {
            abort_if($ex->status === 'blocked', 403, 'Your mentor profile is blocked.');
            DB::table('mentor_profiles')->where('id', $ex->id)->update($d + ['updated_at' => now()]);
            return ['id' => $ex->id, 'status' => $ex->status];
        }
        $id = DB::table('mentor_profiles')->insertGetId(array_merge($d, Area::of($r->user()), ['user_id' => $r->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        UserNotification::notifyAdmins('New mentor for approval: ' . $r->user()->name, null, Area::of($r->user())['pradesh']);
        return response()->json(['id' => $id, 'status' => 'pending', 'message' => 'You will appear in mentors after approval.'], 201);
    }

    public function mentorMe(Request $r)
    {
        $m = DB::table('mentor_profiles')->where('user_id', $r->user()->id)->first();
        return ['mentor' => $m ? $this->mentor($m) + ['status' => $m->status] : null, 'mentee' => DB::table('mentee_profiles')->where('user_id', $r->user()->id)->first()];
    }

    public function menteeRegister(Request $r)
    {
        $d = $r->validate(['stage' => 'required|in:student,graduate,working,switching', 'education' => 'nullable|string|max:150', 'interests' => 'nullable|string|max:300', 'goals' => 'nullable|string|max:2000', 'mode' => 'nullable|in:online,offline,both']);
        $a = Area::of($r->user());
        DB::table('mentee_profiles')->updateOrInsert(['user_id' => $r->user()->id], $d + $a + ['updated_at' => now(), 'created_at' => now()]);
        return ['ok' => true];
    }

    public function mentorRequest(Request $r, int $id)
    {
        $d = $r->validate(['topic' => 'required|string|max:160', 'message' => 'nullable|string|max:1000', 'kind' => 'nullable|in:mentoring,counselling']);
        $m = DB::table('mentor_profiles')->find($id) ?? abort(404);
        return response()->json(['id' => $this->svc->requestMentor($r->user(), $m, $d), 'message' => 'Request sent.'], 201);
    }

    private function ms(Request $r, int $id): object
    {
        $ms = DB::table('mentorships')->find($id) ?? abort(404);
        abort_unless(in_array($r->user()->id, [$ms->mentor_user_id, $ms->mentee_user_id], true), 404);
        return $ms;
    }

    public function mentorships(Request $r)
    {
        $uid = $r->user()->id;
        $rows = DB::table('mentorships as s')->join('mentor_profiles as m', 'm.id', '=', 's.mentor_id')->where(fn ($w) => $w->where('s.mentor_user_id', $uid)->orWhere('s.mentee_user_id', $uid))->orderByDesc('s.id')
            ->get(['s.*', 'm.headline']);
        return $rows->map(fn ($s) => (array) $s + ['role' => $s->mentor_user_id === $uid ? 'mentor' : 'mentee', 'with' => DB::table('users')->where('id', $s->mentor_user_id === $uid ? $s->mentee_user_id : $s->mentor_user_id)->value('name'),
            'next_session' => DB::table('mentor_sessions')->where(['mentorship_id' => $s->id, 'status' => 'scheduled'])->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->value('scheduled_at')])->values();
    }

    public function mentorshipRespond(Request $r, int $id)
    {
        $ms = $this->ms($r, $id); abort_unless($ms->mentor_user_id === $r->user()->id, 403);
        $d = $r->validate(['accept' => 'required|boolean', 'note' => 'nullable|string|max:300']);
        $this->svc->respond($ms, (bool) $d['accept'], $d['note'] ?? null);
        return ['ok' => true];
    }

    public function mentorshipComplete(Request $r, int $id)
    {
        $ms = $this->ms($r, $id); abort_unless($ms->status === 'active', 422, 'Only an active mentorship can be completed.');
        DB::table('mentorships')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]);
        DB::table('mentor_sessions')->where(['mentorship_id' => $id, 'status' => 'scheduled'])->update(['status' => 'cancelled', 'updated_at' => now()]);
        return ['ok' => true];
    }

    public function sessions(Request $r, int $id)
    {
        $this->ms($r, $id);
        return DB::table('mentor_sessions')->where('mentorship_id', $id)->orderBy('scheduled_at')->get();
    }

    public function sessionStore(Request $r, int $id)
    {
        $ms = $this->ms($r, $id);
        $d = $r->validate(['scheduled_at' => 'required|date|after:now', 'duration_min' => 'nullable|integer|min:10|max:240', 'mode' => 'nullable|in:online,offline', 'meeting_link' => 'nullable|url|max:300', 'agenda' => 'nullable|string|max:300']);
        return response()->json(['id' => $this->svc->schedule($ms, $r->user(), $d)], 201);
    }

    public function sessionUpdate(Request $r, int $id, int $sid)
    {
        $ms = $this->ms($r, $id); $s = DB::table('mentor_sessions')->where(['id' => $sid, 'mentorship_id' => $id])->first() ?? abort(404);
        $d = $r->validate(['status' => 'nullable|in:done,cancelled', 'notes' => 'nullable|string|max:3000', 'rating' => 'nullable|integer|min:1|max:5', 'feedback' => 'nullable|string|max:300']);
        $upd = ['updated_at' => now()];
        if (isset($d['status'])) { abort_unless($s->status === 'scheduled', 422, 'The session is already closed.'); $upd['status'] = $d['status']; }
        if (array_key_exists('notes', $d)) { abort_unless($ms->mentor_user_id === $r->user()->id, 403, 'Only the mentor can write notes.'); $upd['notes'] = $d['notes']; }
        if (isset($d['rating'])) { abort_unless($ms->mentee_user_id === $r->user()->id && ($s->status === 'done' || ($upd['status'] ?? null) === 'done'), 422, 'Only the mentee can rate, after the session is complete.'); $upd['rating'] = $d['rating']; $upd['feedback'] = $d['feedback'] ?? null; }
        DB::table('mentor_sessions')->where('id', $sid)->update($upd);
        return ['ok' => true];
    }
}
