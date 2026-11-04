<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Event, UserNotification};
use App\Services\InvestService;
use App\Support\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

/** Member app (Milestone E): investment opportunities, investor / entrepreneur profiles, pitches & showcase, interests, financial literacy webinars. */
class InvestController extends Controller
{
    public function __construct(private InvestService $svc) {}

    private function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    public function meta()
    {
        return ['kinds' => ['startup' => 'Startup', 'real_estate' => 'Real estate', 'bond' => 'Bonds', 'fund' => 'Funds', 'franchise' => 'Franchise', 'partnership' => 'Business partnership'], 'disclaimer' => InvestService::DISCLAIMER];
    }

    /* ---------------------------------------------------------------- curated opportunities */

    public function opportunities(Request $r)
    {
        $r->validate(['kind' => 'nullable|in:startup,real_estate,bond,fund,franchise,partnership', 'sector' => 'nullable|string|max:80', 'q' => 'nullable|string|max:80']);
        return ['disclaimer' => InvestService::DISCLAIMER, 'data' => DB::table('investment_opportunities')->where('status', 'live')->when($r->query('kind'), fn ($q, $v) => $q->where('kind', $v))
            ->when($r->query('sector'), fn ($q, $v) => $q->where('sector', 'like', $this->like($v)))->when($r->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', $this->like($v))->orWhere('summary', 'like', $this->like($v))))
            ->orderByDesc('featured')->orderByDesc('id')->limit(100)->get(['id', 'title', 'kind', 'sector', 'stage', 'ticket_min', 'ticket_max', 'currency', 'expected_return', 'tenure', 'summary', 'featured', 'city', 'interests'])];
    }

    public function opportunity(Request $r, int $id)
    {
        $o = DB::table('investment_opportunities')->where('status', 'live')->find($id) ?? abort(404);
        return (array) $o + ['disclaimer' => InvestService::DISCLAIMER, 'interested' => DB::table('investment_interests')->where(['user_id' => $r->user()->id, 'opportunity_id' => $id])->exists()];
    }

    /* ---------------------------------------------------------------- investor / entrepreneur profile */

    public function profile(Request $r)
    {
        return ['profile' => DB::table('investor_profiles')->where('user_id', $r->user()->id)->first()];
    }

    public function saveProfile(Request $r)
    {
        $d = $r->validate(['role' => 'required|in:investor,entrepreneur,both', 'company' => 'nullable|string|max:150', 'sectors' => 'nullable|string|max:250', 'stage_pref' => 'nullable|string|max:80', 'ticket_min' => 'nullable|numeric|min:0',
            'ticket_max' => 'nullable|numeric|gte:ticket_min', 'bio' => 'nullable|string|max:2000', 'website' => 'nullable|url|max:200', 'linkedin' => 'nullable|url|max:200', 'visible' => 'nullable|boolean']);
        $ex = DB::table('investor_profiles')->where('user_id', $r->user()->id)->first();
        if ($ex) { abort_if($ex->status === 'blocked', 403, 'Your profile is blocked.'); DB::table('investor_profiles')->where('id', $ex->id)->update($d + ['updated_at' => now()]); return ['id' => $ex->id, 'status' => $ex->status]; }
        $id = DB::table('investor_profiles')->insertGetId(array_merge($d, Area::of($r->user()), ['user_id' => $r->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        UserNotification::notifyAdmins('New investor/entrepreneur profile for approval: ' . $r->user()->name, null, Area::of($r->user())['pradesh']);
        return response()->json(['id' => $id, 'status' => 'pending', 'message' => 'It will appear in the directory after approval.'], 201);
    }

    public function investors(Request $r)
    {
        $r->validate(['role' => 'nullable|in:investor,entrepreneur', 'sector' => 'nullable|string|max:80', 'city' => 'nullable|string|max:100']);
        $role = $r->query('role');
        return DB::table('investor_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->where(['p.status' => 'approved', 'p.visible' => true])->where('p.user_id', '!=', $r->user()->id)
            ->when($role, fn ($q) => $q->whereIn('p.role', [$role, 'both']))->when($r->query('sector'), fn ($q, $v) => $q->where('p.sectors', 'like', $this->like($v)))->when($r->query('city'), fn ($q, $v) => $q->where('p.city', 'like', $this->like($v)))
            ->orderByDesc('p.verified')->limit(100)->get(['p.id', 'u.name', 'p.role', 'p.company', 'p.sectors', 'p.stage_pref', 'p.ticket_min', 'p.ticket_max', 'p.bio', 'p.website', 'p.verified', 'p.city', 'p.pradesh']);
    }

    /* ---------------------------------------------------------------- pitches & showcase */

    private function pitch(object $p): array
    {
        return ['id' => $p->id, 'title' => $p->title, 'company' => $p->company, 'sector' => $p->sector, 'stage' => $p->stage, 'ask_amount' => $p->ask_amount, 'equity_pct' => $p->equity_pct, 'currency' => $p->currency,
            'summary' => $p->summary, 'deck_url' => $p->deck_url, 'website' => $p->website, 'showcase' => (bool) $p->showcase, 'interests' => $p->interests, 'city' => $p->city, 'founder' => DB::table('users')->where('id', $p->user_id)->value('name')];
    }

    public function showcase(Request $r)
    {
        return ['disclaimer' => InvestService::DISCLAIMER, 'data' => DB::table('pitches')->where(['status' => 'approved', 'showcase' => true])->orderByDesc('id')->limit(50)->get()->map(fn ($p) => $this->pitch($p))->values()];
    }

    public function pitches(Request $r)
    {
        $r->validate(['sector' => 'nullable|string|max:80', 'stage' => 'nullable|string|max:40', 'q' => 'nullable|string|max:80']);
        return ['disclaimer' => InvestService::DISCLAIMER, 'data' => DB::table('pitches')->where('status', 'approved')->when($r->query('sector'), fn ($q, $v) => $q->where('sector', 'like', $this->like($v)))
            ->when($r->query('stage'), fn ($q, $v) => $q->where('stage', 'like', $this->like($v)))->when($r->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', $this->like($v))->orWhere('summary', 'like', $this->like($v))))
            ->orderByDesc('showcase')->orderByDesc('id')->limit(100)->get()->map(fn ($p) => $this->pitch($p))->values()];
    }

    public function pitchShow(Request $r, int $id)
    {
        $p = DB::table('pitches')->find($id); abort_unless($p && ($p->status === 'approved' || $p->user_id === $r->user()->id), 404);
        return $this->pitch($p) + ['status' => $p->status, 'admin_note' => $p->user_id === $r->user()->id ? $p->admin_note : null, 'disclaimer' => InvestService::DISCLAIMER,
            'interested' => DB::table('investment_interests')->where(['user_id' => $r->user()->id, 'pitch_id' => $id])->exists()];
    }

    public function pitchStore(Request $r)
    {
        $d = $r->validate(['title' => 'required|string|max:200', 'company' => 'nullable|string|max:150', 'sector' => 'nullable|string|max:80', 'stage' => 'nullable|string|max:40', 'ask_amount' => 'nullable|numeric|min:0',
            'equity_pct' => 'nullable|numeric|min:0|max:100', 'summary' => 'required|string|max:4000', 'deck_url' => 'nullable|url|max:300', 'website' => 'nullable|url|max:200', 'deck' => 'nullable|file|mimes:pdf,ppt,pptx|max:10240']);
        abort_if(DB::table('pitches')->where('user_id', $r->user()->id)->whereIn('status', ['pending', 'approved'])->count() >= 5, 422, 'Maximum 5 active pitches.');
        if ($r->hasFile('deck')) $d['deck_url'] = Storage::disk('public')->url($r->file('deck')->store('pitch-decks', 'public'));
        unset($d['deck']);
        $a = Area::of($r->user());
        $id = DB::table('pitches')->insertGetId(array_merge($d, $a, ['user_id' => $r->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        UserNotification::notifyAdmins('New pitch for review: ' . $d['title'], null, $a['pradesh']);
        return response()->json(['id' => $id, 'message' => 'Pitch sent for review.'], 201);
    }

    public function myPitches(Request $r) { return DB::table('pitches')->where('user_id', $r->user()->id)->orderByDesc('id')->get(); }

    public function pitchClose(Request $r, int $id)
    {
        DB::table('pitches')->where(['id' => $id, 'user_id' => $r->user()->id])->update(['status' => 'closed', 'updated_at' => now()]);
        return ['ok' => true];
    }

    /** Suggested investors for my pitch (no contact details; use the in-app flow). */
    public function pitchInvestors(Request $r, int $id)
    {
        $p = DB::table('pitches')->where(['id' => $id, 'user_id' => $r->user()->id])->first() ?? abort(404);
        return collect($this->svc->matchInvestors($p))->map(fn ($x) => ['name' => DB::table('users')->where('id', $x['profile']->user_id)->value('name'), 'company' => $x['profile']->company, 'sectors' => $x['profile']->sectors,
            'ticket_min' => $x['profile']->ticket_min, 'ticket_max' => $x['profile']->ticket_max, 'verified' => (bool) $x['profile']->verified, 'match_score' => $x['score']])->values();
    }

    /* ---------------------------------------------------------------- interest & webinars */

    public function interest(Request $r)
    {
        $d = $r->validate(['opportunity_id' => 'nullable|integer', 'pitch_id' => 'nullable|integer', 'amount' => 'nullable|numeric|min:0', 'message' => 'nullable|string|max:400']);
        return response()->json(['id' => $this->svc->interest($r->user(), $d), 'message' => 'Your interest is recorded. The team/founder will contact you.'], 201);
    }

    public function myInterests(Request $r)
    {
        return DB::table('investment_interests as i')->leftJoin('investment_opportunities as o', 'o.id', '=', 'i.opportunity_id')->leftJoin('pitches as p', 'p.id', '=', 'i.pitch_id')->where('i.user_id', $r->user()->id)->orderByDesc('i.id')
            ->get(['i.id', 'i.amount', 'i.message', 'i.status', 'i.created_at', 'o.title as opportunity', 'p.title as pitch']);
    }

    /** Financial literacy webinars = Events with series "financial_literacy"; RSVP through the normal event RSVP endpoints. */
    public function webinars(Request $r)
    {
        return Event::query()->where('series', 'financial_literacy')->whereDate('event_date', '>=', today())->orderBy('event_date')->limit(50)
            ->get(['id', 'title', 'description', 'venue', 'event_date', 'event_time', 'series'])->map(fn ($e) => $e->toArray() + ['rsvp_url' => url("/api/v1/m/rsvp/events/{$e->id}")])->values();
    }
}
