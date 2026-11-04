<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Family, JobPost, JobProfile, MatrimonyProfile};
use App\Services\{AiService, PartnerService, RsvpService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: AI search + AI matches for Matrimony and Jobs, drafting helpers and keyword search across the whole platform. */
class AiController extends Controller
{
    public function __construct(private AiService $ai, private MatrimonyController $mat, private JobController $jobs) {}

    private static function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    // ------------------------------------------------ matrimony
    private function candidates(MatrimonyProfile $me)
    {
        return MatrimonyProfile::withoutGlobalScopes()->where('status', 'paid')->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '>=', today()))->where('visible', true)
            ->where('id', '!=', $me->id)->where('gender', strcasecmp((string) $me->gender, 'male') === 0 ? 'Female' : 'Male')
            ->where(fn ($w) => $w->whereNull('gotra')->orWhere('gotra', '!=', (string) $me->gotra));
    }

    private function rankProfiles(MatrimonyProfile $me, $rows, array $extra = [])
    {
        return $rows->map(function (MatrimonyProfile $p) use ($me, $extra) {
            $m = $this->ai->matchScore($me, $p); $bonus = $extra[$p->id] ?? 0;
            return ['p' => $p, 'm' => ['score' => min(99, $m['score'] + $bonus)] + $m];
        })->sortByDesc(fn ($x) => $x['m']['score'])->values();
    }

    private function shapeProfiles(Request $r, MatrimonyProfile $me, $ranked)
    {
        [$short, $interest] = $this->mat->relations($me, $ranked->pluck('p.id'));
        return $ranked->map(fn ($x) => $this->mat->card($x['p'], $short, $interest) + ['match' => ['score' => $x['m']['score'], 'reasons' => $x['m']['reasons'], 'concerns' => $x['m']['concerns']]])->values();
    }

    /** GET matrimony/ai-search?text=...&profile_id= : "CA ladka Jaipur 28 saal veg" -> filtered + ranked by compatibility */
    public function matrimonySearch(Request $r)
    {
        $r->validate(['text' => 'required|string|min:2|max:200']);
        $me = $this->mat->acting($r);
        $places = MatrimonyProfile::withoutGlobalScopes()->where('status', 'paid')->select('city', 'state')->distinct()->limit(600)->get()->flatMap(fn ($x) => [mb_strtolower((string) $x->city), mb_strtolower((string) $x->state)])->filter()->unique()->values()->all();
        $f = $this->ai->understand($r->input('text'), 'matrimony', $places);
        $q = $this->candidates($me);
        if (isset($f['age_min'])) $q->where('dob', '<=', now()->subYears((int) $f['age_min'])->toDateString());
        if (isset($f['age_max'])) $q->where('dob', '>=', now()->subYears((int) $f['age_max'] + 1)->addDay()->toDateString());
        if (isset($f['height_min'])) $q->where('height_cm', '>=', (int) $f['height_min']);
        if (isset($f['income_min'])) $q->where('annual_income', '>=', (int) $f['income_min']);
        if (($f['manglik'] ?? null) === 'yes') $q->where('manglik', true); if (($f['manglik'] ?? null) === 'no') $q->where('manglik', false);
        if (isset($f['diet'])) $q->where('diet', 'like', self::like($f['diet']) );
        if (isset($f['marital'])) $q->where('marital_status', 'like', self::like($f['marital']));
        if (! empty($f['places'])) $q->where(fn ($w) => $w->whereIn(DB::raw('LOWER(city)'), $f['places'])->orWhereIn(DB::raw('LOWER(state)'), $f['places']));
        $extra = [];
        $rows = $q->orderByDesc('registered_at')->limit(300)->get();
        if (! empty($f['keywords'])) {                                  // keywords must hit qualification / degree / occupation / about / gotra; each hit adds to the rank
            $exp = $this->ai->expand($f['keywords']);
            $rows = $rows->filter(function ($p) use ($exp, &$extra) {
                $hay = mb_strtolower(implode(' ', [$p->qualification, $p->degree, $p->occupation, $p->occupation_type, $p->about, $p->gotra, $p->name]));
                $hits = 0; foreach ($exp as $alts) foreach ($alts as $a) if (str_contains($hay, $a)) { $hits++; break; }
                $extra[$p->id] = $hits * 6; return $hits > 0;
            });
        }
        $ranked = $this->rankProfiles($me, $rows, $extra)->take(30);
        return ['understood' => $f, 'ai' => $this->ai->enabled(), 'data' => $this->shapeProfiles($r, $me, $ranked)];
    }

    /** GET matrimony/ai-matches?profile_id= : best matches for me with the reasons */
    public function matrimonyMatches(Request $r)
    {
        $me = $this->mat->acting($r);
        $pref = (array) $me->partner_pref; $q = $this->candidates($me);
        if (isset($pref['age_min'])) $q->where('dob', '<=', now()->subYears((int) $pref['age_min'] - 3)->toDateString());           // soft window: preferences refine the score
        if (isset($pref['age_max'])) $q->where('dob', '>=', now()->subYears((int) $pref['age_max'] + 4)->toDateString());
        $ranked = $this->rankProfiles($me, $q->orderByDesc('registered_at')->limit(300)->get())->filter(fn ($x) => $x['m']['score'] >= 45)->take(20);
        return ['data' => $this->shapeProfiles($r, $me, $ranked)];
    }

    /** GET matrimony/{id}/ai-compatibility?profile_id= : why this profile suits me */
    public function compatibility(Request $r, int $id)
    {
        $me = $this->mat->acting($r);
        $p = $this->candidates($me)->findOrFail($id);
        return ['data' => $this->ai->matchScore($me, $p)];
    }

    /** POST matrimony/ai-about : drafts the "about me" text (member edits and saves it with POST matrimony/me) */
    public function about(Request $r)
    {
        $me = $this->mat->acting($r, false);
        return ['text' => $this->ai->draftAbout($me->only(['name', 'dob', 'qualification', 'occupation', 'city', 'diet']) + ['gender' => $me->gender])];
    }

    // ------------------------------------------------ jobs
    private function liveJobs()
    {
        return JobPost::withoutGlobalScopes()->live();
    }

    /** GET jobs/ai-search?text= : "part time accountant Pune 20k", "fresher work from home" */
    public function jobsSearch(Request $r)
    {
        $r->validate(['text' => 'required|string|min:2|max:200']);
        $places = $this->liveJobs()->select('city', 'state')->distinct()->limit(600)->get()->flatMap(fn ($x) => [mb_strtolower((string) $x->city), mb_strtolower((string) $x->state)])->filter()->unique()->values()->all();
        $f = $this->ai->understand($r->input('text'), 'job', $places);
        $q = $this->liveJobs();
        if (isset($f['type'])) $q->where('job_type', $f['type']);
        if (isset($f['work_mode'])) $q->where('work_mode', $f['work_mode']);
        if (isset($f['exp'])) $q->where(fn ($w) => $w->whereNull('exp_min')->orWhere('exp_min', '<=', (int) $f['exp']));
        if (isset($f['salary_min'])) $q->where(fn ($w) => $w->whereNull('salary_max')->orWhere('salary_max', '>=', (int) $f['salary_min']));
        if (! empty($f['places'])) $q->where(fn ($w) => $w->whereIn(DB::raw('LOWER(city)'), $f['places'])->orWhereIn(DB::raw('LOWER(state)'), $f['places']));
        $rows = $q->orderByDesc('id')->limit(300)->get(); $hits = [];
        if (! empty($f['keywords'])) {
            $exp = $this->ai->expand($f['keywords']);
            $rows = $rows->filter(function ($j) use ($exp, &$hits) {
                $hay = mb_strtolower(implode(' ', [$j->title, $j->company, $j->skills, $j->category, $j->description]));
                $n = 0; foreach ($exp as $alts) foreach ($alts as $a) if (str_contains($hay, $a)) { $n++; break; }
                $hits[$j->id] = $n; return $n > 0;
            });
        }
        $mine = JobProfile::where('user_id', $r->user()->id)->first();
        $out = $rows->map(function ($j) use ($mine, $hits) {
            $m = $mine ? $this->ai->jobScore($mine, $j) : ['score' => null, 'reasons' => [], 'concerns' => []];
            return ['j' => $j, 'm' => $m, 'rank' => ($hits[$j->id] ?? 0) * 100 + ($m['score'] ?? 0)];
        })->sortByDesc('rank')->take(30)->values();
        return ['understood' => $f, 'ai' => $this->ai->enabled(), 'data' => $out->map(fn ($x) => $this->jobs->item($x['j']) + ['match' => $x['m']])->values()];
    }

    /** GET jobs/ai-matches : jobs ranked for my job profile, with reasons */
    public function jobsMatches(Request $r)
    {
        $mine = JobProfile::where('user_id', $r->user()->id)->first();
        abort_unless($mine, 422, 'Create your job profile first.');
        $own = $r->user()->id;
        $rows = $this->liveJobs()->where('user_id', '!=', $own)->when($mine->gender, fn ($q, $g) => $q->whereIn('gender_pref', ['any', strtolower($g)]))->orderByDesc('id')->limit(300)->get();
        $applied = DB::table('job_applications')->where('user_id', $own)->pluck('job_id')->all();
        $out = $rows->map(fn ($j) => ['j' => $j, 'm' => $this->ai->jobScore($mine, $j)])->filter(fn ($x) => $x['m']['score'] >= 45)->sortByDesc(fn ($x) => $x['m']['score'])->take(20)->values();
        return ['data' => $out->map(fn ($x) => $this->jobs->item($x['j']) + ['applied' => in_array($x['j']->id, $applied, true), 'match' => $x['m']])->values()];
    }

    /** POST jobs/ai-description {title, skills?, city?, company?, experience?} : drafts the description for a job post */
    public function jobDescription(Request $r)
    {
        $d = $r->validate(['title' => 'required|string|max:120', 'skills' => 'nullable|string|max:300', 'city' => 'nullable|string|max:100', 'company' => 'nullable|string|max:150', 'experience' => 'nullable|string|max:60']);
        return ['text' => $this->ai->draftJob($d)];
    }

    /** GET partners/{id}/ai-rank?job_id= : applicants of my job ranked by fit (recruiter; needs a live subscription for contact details) */
    public function rankApplicants(Request $r, int $id, PartnerService $svc)
    {
        $p = DB::table('partners')->where(['id' => $id, 'user_id' => $r->user()->id, 'kind' => 'recruiter'])->first() ?? abort(404);
        $sub = $svc->gate($p, false);
        $job = JobPost::withoutGlobalScopes()->where(['id' => (int) $r->query('job_id'), 'partner_id' => $id])->first() ?? abort(404);
        $rows = DB::table('job_applications as a')->join('users as u', 'u.id', '=', 'a.user_id')->where('a.job_id', $job->id)->get(['a.id', 'a.user_id', 'a.status', 'a.note', 'u.name', 'u.mobile']);
        $profiles = JobProfile::whereIn('user_id', $rows->pluck('user_id'))->get()->keyBy('user_id');
        $out = $rows->map(function ($a) use ($profiles, $job) {
            $pr = $profiles[$a->user_id] ?? null; $m = $pr ? $this->ai->jobScore($pr, $job) : ['score' => 0, 'reasons' => [], 'concerns' => ['Job profile not filled']];
            return (array) $a + ['headline' => $pr?->headline, 'experience_years' => $pr?->experience_years, 'skills' => $pr?->skills, 'city' => $pr?->city, 'match' => $m];
        })->sortByDesc(fn ($x) => $x['match']['score'])->values();
        return ['job' => $job->title, 'data' => $out];
    }

    // ------------------------------------------------ keyword search across the platform
    /** GET search?q=&types[]=business,job,event,vendor,place,product,news,partner&city=&state=&country= */
    public function search(Request $r, RsvpService $rsvp)
    {
        $r->validate(['q' => 'required|string|min:2|max:100', 'types' => 'nullable|array', 'types.*' => 'in:business,job,event,vendor,place,product,news,partner', 'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100', 'country' => 'nullable|string|max:100']);
        $exp = $this->ai->expand(AiService::tokens($r->input('q')));
        abort_if(! $exp, 422, 'No keyword found.');
        $types = $r->input('types') ?: ['business', 'job', 'event', 'vendor', 'place', 'product', 'news', 'partner'];
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $city = $r->input('city'); $state = $r->input('state'); $country = $r->input('country');
        $match = function ($q, array $cols) use ($exp) {                // every typed word must match (word or its Hinglish synonym) in at least one column -> AND of ORs; falls back to any-word below
            foreach ($exp as $alts) $q->where(function ($w) use ($alts, $cols) { foreach ($alts as $a) foreach ($cols as $c) $w->orWhere($c, 'like', self::like($a)); });
            return $q;
        };
        $score = function (array $texts) use ($exp) {
            $hay = mb_strtolower(implode(' ', array_filter($texts))); $n = 0; foreach ($exp as $k => $alts) { if (str_contains($hay, $k)) $n += 2; else foreach ($alts as $a) if (str_contains($hay, $a)) { $n++; break; } } return $n;
        };
        $res = [];
        $geo = function ($q, ?string $c = 'city', ?string $s = 'state', ?string $co = 'country') use ($city, $state, $country) {
            if ($city && $c) $q->where($c, $city); if ($state && $s) $q->where($s, $state); if ($country && $co) $q->where($co, $country);
            return $q;
        };
        if (in_array('business', $types, true)) foreach ($geo($match(DB::table('businesses')->where('status', 'active'), ['name', 'type', 'description']), 'city', 'pradesh')->limit(40)->get() as $b)
            $res[] = ['type' => 'business', 'id' => $b->id, 'title' => $b->name, 'subtitle' => $b->type, 'city' => $b->city, 'score' => $score([$b->name, $b->type, $b->description]) + ($b->seller_verified ? 1 : 0)];
        if (in_array('product', $types, true)) foreach ($match(DB::table('products')->where('status', 'active'), ['title', 'description'])->limit(30)->get() as $x)
            $res[] = ['type' => 'product', 'id' => $x->id, 'title' => $x->title, 'subtitle' => ($x->currency ?: 'INR') . ' ' . $x->price, 'city' => null, 'score' => $score([$x->title, $x->description])];
        if (in_array('job', $types, true)) foreach ($geo($match($this->liveJobs(), ['title', 'company', 'skills', 'category', 'description']), 'city', 'state', 'country')->limit(40)->get() as $j)
            $res[] = ['type' => 'job', 'id' => $j->id, 'title' => $j->title, 'subtitle' => $j->company, 'city' => $j->city, 'score' => $score([$j->title, $j->skills, $j->category, $j->company])];
        if (in_array('event', $types, true)) foreach ($geo($match(DB::table('events')->whereDate('event_date', '>=', today()), ['title', 'description', 'venue']), 'city', 'state', 'country')->limit(40)->get() as $e) {
            $ev = \App\Models\Event::find($e->id); if (! $ev || ! $rsvp->visibleTo($ev, $f)) continue;
            $res[] = ['type' => 'event', 'id' => $e->id, 'title' => $e->title, 'subtitle' => $e->event_date . ' · ' . $e->venue, 'city' => $e->city, 'score' => $score([$e->title, $e->venue, $e->description])];
        }
        $liveVendor = fn ($q) => config('abmm.listing_requires_subscription') ? $q->whereDate('listing_until', '>=', today()) : $q;
        if (in_array('vendor', $types, true)) foreach ($geo($match($liveVendor(DB::table('event_vendors')->where('status', 'active')), ['name', 'category', 'description', 'services']), 'city', null, 'country')->limit(40)->get() as $v)
            $res[] = ['type' => 'vendor', 'id' => $v->id, 'title' => $v->name, 'subtitle' => $v->category, 'city' => $v->city, 'score' => $score([$v->name, $v->category, $v->description, $v->services]) + ($v->verified ? 1 : 0)];
        if (in_array('place', $types, true)) foreach ($geo($match(DB::table('directory_entries')->where('status', 'active'), ['name', 'speciality', 'kind', 'description', 'facilities']), 'city', 'state', 'country')->limit(40)->get() as $d)
            $res[] = ['type' => 'place', 'id' => $d->id, 'title' => $d->name, 'subtitle' => trim($d->kind . ' ' . $d->speciality), 'city' => $d->city, 'score' => $score([$d->name, $d->kind, $d->speciality, $d->description]) + ($d->verified ? 1 : 0)];
        if (in_array('news', $types, true)) foreach ($geo($match(DB::table('feed_posts')->where('status', 'approved'), ['title', 'body']), 'city', 'state', 'country')->limit(20)->get() as $n)
            $res[] = ['type' => 'news', 'id' => $n->id, 'title' => $n->title, 'subtitle' => $n->type, 'city' => $n->city, 'score' => $score([$n->title, $n->body])];
        if (in_array('partner', $types, true)) foreach ($geo($match(DB::table('partners')->where('status', 'active'), ['name', 'description']), 'city', 'pradesh', 'country')->limit(20)->get() as $p)
            $res[] = ['type' => 'partner', 'id' => $p->id, 'title' => $p->name, 'subtitle' => PartnerService::KINDS[$p->kind]['label'], 'city' => $p->city, 'score' => $score([$p->name, $p->description, $p->kind]) + ($p->verified ? 1 : 0)];
        $data = collect($res)->sortByDesc('score')->values();
        return ['q' => $r->input('q'), 'total' => $data->count(), 'facets' => $data->countBy('type'), 'data' => $data->take(60)->values()];
    }
}
