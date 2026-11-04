<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Event, Family, Member};
use App\Services\{CorrectionService, GeoTarget, PointsService, RsvpService, StreakService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Member app (engagement): streak, polls & surveys, event RSVP + QR pass, points / badges / leaderboard / rewards / certificates, data-correction requests. */
class EngageController extends Controller
{
    public function __construct(private PointsService $points) {}

    private function family(Request $r): Family { return Family::withoutGlobalScopes()->findOrFail($r->user()->family_id); }

    // ---------------- streak ----------------
    public function ping(Request $r, StreakService $s) { return $s->ping($r->user()); }

    // ---------------- polls & surveys ----------------
    private function openPolls(Family $f)
    {
        return DB::table('polls')->where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->orderByDesc('id')->get()
            ->filter(fn ($p) => GeoTarget::matches(json_decode($p->target ?: '[]', true) ?: [], $f))->values();
    }

    public function polls(Request $r)
    {
        $f = $this->family($r);
        $done = DB::table('poll_responses')->where('user_id', $r->user()->id)->pluck('poll_id')->all();
        return $this->openPolls($f)->map(fn ($p) => ['id' => $p->id, 'kind' => $p->kind, 'title' => $p->title, 'description' => $p->description, 'ends_at' => $p->ends_at, 'answered' => in_array($p->id, $done, true)]);
    }

    public function poll(Request $r, int $id)
    {
        $p = $this->openPolls($this->family($r))->firstWhere('id', $id) ?? abort(404);
        $qs = DB::table('poll_questions')->where('poll_id', $id)->orderBy('sort')->get()->map(fn ($q) => ['id' => $q->id, 'text' => $q->text, 'type' => $q->type, 'options' => json_decode($q->options ?: '[]', true), 'required' => (bool) $q->required]);
        $mine = DB::table('poll_responses')->where(['poll_id' => $id, 'user_id' => $r->user()->id])->first();
        return ['id' => $p->id, 'kind' => $p->kind, 'title' => $p->title, 'description' => $p->description, 'questions' => $qs, 'answered' => (bool) $mine,
            'results' => $mine && $p->show_results ? $this->results($id) : null];
    }

    private function results(int $id): array
    {
        $out = [];
        $resp = DB::table('poll_responses')->where('poll_id', $id)->pluck('answers');
        foreach (DB::table('poll_questions')->where('poll_id', $id)->orderBy('sort')->get() as $q) {
            if (! in_array($q->type, ['single', 'multi', 'rating'], true)) continue;
            $tally = [];
            foreach ($resp as $a) { $v = (json_decode($a, true) ?: [])[$q->id] ?? null; foreach ((array) $v as $x) if ($x !== null && $x !== '') $tally[$x] = ($tally[$x] ?? 0) + 1; }
            $out[] = ['question_id' => $q->id, 'text' => $q->text, 'total' => $resp->count(), 'tally' => $tally];
        }
        return $out;
    }

    public function vote(Request $r, int $id)
    {
        $f = $this->family($r);
        $p = $this->openPolls($f)->firstWhere('id', $id) ?? abort(404);
        $answers = (array) $r->input('answers', []);
        $clean = []; $err = [];
        foreach (DB::table('poll_questions')->where('poll_id', $id)->get() as $q) {
            $a = $answers[$q->id] ?? null; $opts = json_decode($q->options ?: '[]', true);
            $empty = $a === null || $a === '' || $a === [];
            if ($empty) { if ($q->required) $err["answers.{$q->id}"] = 'This question is required.'; continue; }
            switch ($q->type) {
                case 'single': if (! is_string($a) || ! in_array($a, $opts, true)) $err["answers.{$q->id}"] = 'Invalid option.'; else $clean[$q->id] = $a; break;
                case 'multi': if (! is_array($a) || array_diff($a, $opts)) $err["answers.{$q->id}"] = 'Invalid option.'; else $clean[$q->id] = array_values(array_unique($a)); break;
                case 'rating': if (! is_numeric($a) || $a < 1 || $a > 5) $err["answers.{$q->id}"] = 'Choose 1 to 5.'; else $clean[$q->id] = (int) $a; break;
                default: $clean[$q->id] = mb_substr(trim((string) $a), 0, 1000);
            }
        }
        if ($err) throw ValidationException::withMessages($err);
        $ins = DB::table('poll_responses')->insertOrIgnore(['poll_id' => $id, 'user_id' => $r->user()->id, 'family_id' => $f->id, 'country' => $f->country, 'state' => $f->state, 'district' => $f->district, 'city' => $f->city,
            'answers' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        abort_unless($ins, 422, 'You have already answered.');
        $pts = $this->points->award($r->user(), $p->kind === 'survey' ? 'survey_complete' : 'poll_vote', (string) $id);
        return ['message' => 'Dhanyavaad!', 'points' => $pts, 'results' => $p->show_results ? $this->results($id) : null];
    }

    // ---------------- events: RSVP + QR pass ----------------
    public function events(Request $r, RsvpService $svc)
    {
        $f = $this->family($r);
        $mine = DB::table('event_rsvps')->where('user_id', $r->user()->id)->get()->keyBy('event_id');
        return Event::where('rsvp_enabled', true)->whereDate('event_date', '>=', today())->orderBy('event_date')->get()->filter(fn ($e) => $svc->visibleTo($e, $f))->values()->map(fn ($e) => [
            'id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'venue' => $e->venue, 'date' => $e->event_date->toDateString(), 'time' => $e->event_time,
            'seats_left' => $e->capacity ? max(0, $e->capacity - $svc->used($e->id)) : null, 'max_guests' => $e->max_guests,
            'rsvp' => ($m = $mine[$e->id] ?? null) ? ['status' => $m->status, 'guests' => $m->guests, 'qr' => $m->status === 'going' ? RsvpService::qr($e->id, $m->pass_code) : null, 'checked_in' => (bool) $m->checked_in_at] : null,
        ]);
    }

    /** GET events/nearby?scope=city|state|all : upcoming events visible to my family, nearest first (my city, then my state, then the rest). */
    public function nearby(Request $r, RsvpService $svc)
    {
        $d = $r->validate(['scope' => 'nullable|in:city,state,all']);
        $f = $this->family($r); $scope = $d['scope'] ?? 'all';
        $mine = DB::table('event_rsvps')->where('user_id', $r->user()->id)->pluck('status', 'event_id');
        $same = fn ($a, $b) => $a && $b && strcasecmp(trim((string) $a), trim((string) $b)) === 0;
        $rows = Event::whereDate('event_date', '>=', today())->where('is_public', true)->orderBy('event_date')->limit(300)->get()->filter(fn ($e) => $svc->visibleTo($e, $f))->map(function ($e) use ($f, $same, $mine) {
            $rank = $same($e->city, $f->city) ? 0 : ($same($e->state, $f->state) || $same($e->audience_value, $f->state) ? 1 : 2);
            return ['id' => $e->id, 'title' => $e->title, 'venue' => $e->venue, 'city' => $e->city, 'state' => $e->state, 'date' => $e->event_date->toDateString(), 'time' => $e->event_time, 'rsvp_enabled' => (bool) $e->rsvp_enabled,
                'ticketing' => (bool) $e->ticketing, 'online' => (bool) $e->online_provider, 'my_rsvp' => $mine[$e->id] ?? null, 'nearness' => ['city', 'state', 'other'][$rank], '_rank' => $rank];
        })->when($scope !== 'all', fn ($c) => $c->filter(fn ($x) => $x['_rank'] <= ($scope === 'city' ? 0 : 1)))->sortBy([['_rank', 'asc'], ['date', 'asc']])->values()
            ->map(function ($x) { unset($x['_rank']); return $x; });
        return ['city' => $f->city, 'state' => $f->state, 'data' => $rows];
    }

    public function rsvp(Request $r, int $id, RsvpService $svc)
    {
        $d = $r->validate(['status' => 'required|in:going,declined', 'guests' => 'nullable|integer|min:0|max:10']);
        [$st, $payload] = $svc->rsvp(Event::findOrFail($id), $r->user(), $d['status'], (int) ($d['guests'] ?? 0));
        return response()->json($payload, $st === 'ok' ? 200 : 422);
    }

    public function myPasses(Request $r)
    {
        return DB::table('event_rsvps as r')->join('events as e', 'e.id', '=', 'r.event_id')->where('r.user_id', $r->user()->id)->where('r.status', 'going')->orderByDesc('e.event_date')->limit(50)
            ->get(['r.event_id', 'r.guests', 'r.pass_code', 'r.checked_in_at', 'e.title', 'e.venue', 'e.event_date'])->map(fn ($x) => (array) $x + ['qr' => RsvpService::qr($x->event_id, $x->pass_code)]);
    }

    // ---------------- points, badges, leaderboard, rewards, certificates ----------------
    public function pointsSummary(Request $r)
    {
        $uid = $r->user()->id; $s = DB::table('member_streaks')->where('user_id', $uid)->first();
        return ['balance' => $this->points->balance($uid), 'earned' => $this->points->earned($uid), 'streak' => (int) ($s->current ?? 0), 'longest_streak' => (int) ($s->longest ?? 0),
            'badges' => DB::table('user_badges as ub')->join('badges as b', 'b.id', '=', 'ub.badge_id')->where('ub.user_id', $uid)->get(['b.code', 'b.name', 'b.description', 'ub.ref', 'ub.created_at']),
            'rules' => DB::table('point_rules')->where('is_active', true)->get(['action', 'label', 'points', 'daily_cap']),
            'history' => DB::table('point_ledger')->where('user_id', $uid)->orderByDesc('id')->limit(30)->get(['action', 'points', 'note', 'created_at'])];
    }

    public function leaderboard(Request $r)
    {
        $f = $this->family($r);
        $scope = $r->input('scope', 'country');
        $geo = match ($scope) { 'city' => ['country' => $f->country, 'city' => $f->city], 'zone' => ['country' => $f->country, 'city' => $f->city, 'zone' => $f->zone], 'world' => [], default => ['country' => $f->country] };
        $rows = $this->points->leaderboard($r->input('period') === 'all' ? 'all' : 'month', $geo, 50);
        return $rows->values()->map(fn ($x, $i) => ['rank' => $i + 1, 'name' => $x->name, 'city' => $x->city, 'points' => (int) $x->points, 'me' => $x->user_id === $r->user()->id]);
    }

    public function rewards(Request $r)
    {
        return ['balance' => $this->points->balance($r->user()->id), 'rewards' => DB::table('rewards')->where('is_active', true)->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '>', 0))->orderBy('cost')->get(['id', 'name', 'description', 'cost', 'stock'])];
    }

    public function redeem(Request $r)
    {
        $d = $r->validate(['reward_id' => 'required|integer']);
        $u = $r->user();
        return DB::transaction(function () use ($d, $u) {
            $w = DB::table('rewards')->where('id', $d['reward_id'])->where('is_active', true)->lockForUpdate()->first();
            abort_unless($w, 404);
            abort_if($w->stock !== null && $w->stock < 1, 422, 'Stock khatam.');
            DB::table('users')->where('id', $u->id)->lockForUpdate()->first();     // serialise this user's redemptions
            abort_if($this->points->balance($u->id) < $w->cost, 422, 'Not enough points.');
            $rid = DB::table('redemptions')->insertGetId(['reward_id' => $w->id, 'user_id' => $u->id, 'family_id' => $u->family_id, 'points' => $w->cost, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('point_ledger')->insert(['user_id' => $u->id, 'family_id' => $u->family_id, 'action' => 'redeem', 'ref' => (string) $rid, 'points' => -$w->cost, 'note' => $w->name, 'created_at' => now()]);
            if ($w->stock !== null) DB::table('rewards')->where('id', $w->id)->decrement('stock');
            return ['message' => 'Request sent.', 'redemption_id' => $rid, 'balance' => $this->points->balance($u->id)];
        });
    }

    public function myRedemptions(Request $r)
    {
        return DB::table('redemptions as d')->join('rewards as w', 'w.id', '=', 'd.reward_id')->where('d.user_id', $r->user()->id)->orderByDesc('d.id')->get(['d.id', 'w.name', 'd.points', 'd.status', 'd.remark', 'd.created_at']);
    }

    public function certificates(Request $r)
    {
        return DB::table('certificates')->where('user_id', $r->user()->id)->orderByDesc('id')->get(['id', 'number', 'type', 'title', 'detail', 'created_at']);
    }

    public function certificate(Request $r, int $id)
    {
        $c = DB::table('certificates')->where(['id' => $id, 'user_id' => $r->user()->id])->first();
        abort_unless($c && $c->path && is_file(storage_path('app/private/' . $c->path)), 404);
        return response()->file(storage_path('app/private/' . $c->path), ['Content-Type' => 'application/pdf']);
    }

    // ---------------- data-correction requests ----------------
    public function correctionCreate(Request $r, CorrectionService $svc)
    {
        $d = $r->validate(['target' => 'required|in:family,member', 'member_id' => 'nullable|integer', 'field' => 'required|string|max:40', 'new_value' => 'required', 'reason' => 'nullable|string|max:255']);
        $u = $r->user(); $f = $this->family($r);
        $head = (bool) Member::withoutGlobalScopes()->whereKey($u->member_id)->value('is_head');
        if ($d['target'] === 'family') {
            abort_unless($head, 403, 'Only the Head of Family can change family details.');
            $memberId = null;
        } else {
            $memberId = (int) ($d['member_id'] ?? $u->member_id);
            abort_unless(Member::withoutGlobalScopes()->where('family_id', $f->id)->whereKey($memberId)->exists(), 404);
            abort_unless($head || $memberId === (int) $u->member_id, 403, 'You can only request changes to your own details.');
        }
        $new = $svc->validate($d['field'], $d['new_value']);
        $old = $svc->current($d['target'], $d['target'] === 'family' ? $f->id : $memberId, $d['field']);
        abort_if($old === $new, 422, 'The new value is the same as the old one.');
        abort_if(DB::table('data_corrections')->where(['family_id' => $f->id, 'member_id' => $memberId, 'field' => $d['field'], 'status' => 'pending'])->exists(), 422, 'A request for this field is already pending.');
        $id = DB::table('data_corrections')->insertGetId(['family_id' => $f->id, 'member_id' => $memberId, 'user_id' => $u->id, 'target' => $d['target'], 'field' => $d['field'], 'old_value' => $old, 'new_value' => $new,
            'reason' => $d['reason'] ?? null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        \App\Models\UserNotification::notifyAdmins("Data correction request: {$d['field']} ({$f->head_name})", route('admin.corrections'), $f->pradesh);
        return ['id' => $id, 'status' => 'pending'];
    }

    public function corrections(Request $r)
    {
        return DB::table('data_corrections')->where('family_id', $r->user()->family_id)->orderByDesc('id')->limit(100)->get(['id', 'target', 'member_id', 'field', 'old_value', 'new_value', 'status', 'remark', 'created_at']);
    }

    public function correctableFields() { return array_keys(CorrectionService::FIELDS); }

    // ---------------- health flags (used by notification filters) ----------------
    public function health(Request $r)
    {
        $d = $r->validate(['member_id' => 'nullable|integer', 'vaccinated' => 'nullable|boolean', 'covid_recovered' => 'nullable|boolean']);
        $u = $r->user(); $mid = (int) ($d['member_id'] ?? $u->member_id);
        $head = (bool) Member::withoutGlobalScopes()->whereKey($u->member_id)->value('is_head');
        abort_unless($mid === (int) $u->member_id || $head, 403);
        $m = Member::withoutGlobalScopes()->where('family_id', $u->family_id)->findOrFail($mid);
        $m->update(array_intersect_key($d, array_flip(['vaccinated', 'covid_recovered'])));
        return ['vaccinated' => $m->vaccinated, 'covid_recovered' => $m->covid_recovered];
    }
}
