<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AcademyService;
use App\Services\Payments\PaymentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app (Milestone F): Learning Academy - courses of short videos, progress, live classes, knowledge library. */
class AcademyController extends Controller
{
    public function __construct(private AcademyService $svc, private PaymentLedger $ledger) {}

    private function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    public function meta(Request $r)
    {
        return ['segments' => AcademyService::SEGMENTS, 'subscribed' => $this->svc->subscribed($r->user()->id),
            'plans' => Plan::active()->where('audience', 'training')->orderBy('price')->get(['id', 'name', 'period', 'price', 'currency', 'features'])];
    }

    private function course(object $c, int $uid, bool $detail = false): array
    {
        $access = $this->svc->hasAccess($uid, $c);
        return ['id' => $c->id, 'title' => $c->title, 'segment' => $c->segment, 'category' => $c->category, 'description' => $c->description, 'thumbnail' => $c->thumbnail, 'instructor' => $c->instructor_name, 'lang' => $c->lang,
            'price' => (float) $c->price, 'currency' => $c->currency, 'free' => (bool) $c->free, 'in_subscription' => (bool) $c->subscription, 'has_access' => $access, 'enrolments' => $c->enrolments,
            'lessons' => DB::table('lessons')->where(['course_id' => $c->id, 'status' => 'live'])->count(), 'progress' => $this->svc->courseProgress($uid, $c->id)];
    }

    public function courses(Request $r)
    {
        $r->validate(['segment' => 'nullable|in:school,college,business,family,general', 'q' => 'nullable|string|max:80', 'category' => 'nullable|string|max:60', 'free' => 'nullable|boolean']);
        return DB::table('courses')->where('status', 'live')->when($r->query('segment'), fn ($q, $v) => $q->whereIn('segment', [$v, 'general']))->when($r->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($r->boolean('free'), fn ($q) => $q->where('free', true))->when($r->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', $this->like($v))->orWhere('description', 'like', $this->like($v))))
            ->orderBy('sort')->orderByDesc('id')->limit(100)->get()->map(fn ($c) => $this->course($c, $r->user()->id))->values();
    }

    public function show(Request $r, int $id)
    {
        $c = DB::table('courses')->where('status', 'live')->find($id) ?? abort(404);
        $uid = $r->user()->id;
        $done = DB::table('lesson_progress')->where(['user_id' => $uid, 'course_id' => $id])->pluck('percent', 'lesson_id');
        $lessons = DB::table('lessons')->where(['course_id' => $id, 'status' => 'live'])->orderBy('sort')->orderBy('id')->get()->map(fn ($l) => ['id' => $l->id, 'title' => $l->title, 'duration_sec' => $l->duration_sec, 'free_preview' => (bool) $l->free_preview,
            'summary' => $l->summary, 'locked' => ! $this->svc->canWatch($uid, $c, $l), 'percent' => (int) ($done[$l->id] ?? 0)]);
        return $this->course($c, $uid, true) + ['lesson_list' => $lessons, 'live_classes' => DB::table('live_classes')->where('course_id', $id)->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->get(['id', 'title', 'starts_at', 'duration_min'])];
    }

    public function enrol(Request $r, int $id)
    {
        $d = $r->validate(['coupon' => 'nullable|string|max:40']);
        $c = DB::table('courses')->find($id) ?? abort(404);
        if (! $c->free && ! $this->svc->hasAccess($r->user()->id, $c) && $c->price > 0 && ($ios = \App\Support\IosPolicy::guard($r, 'course', 'course', $c->id))) return $ios;   // iPhone: Apple In-App Purchase rule
        [$eid, $p] = $this->svc->enrol($r->user(), $c, $d['coupon'] ?? null);
        return $p ? ['enrolment_id' => $eid, 'payment_no' => $p->number, 'checkout' => $this->ledger->checkout($p, ['title' => $c->title])] : ['enrolment_id' => $eid, 'enrolled' => true];
    }

    public function lesson(Request $r, int $id)
    {
        $l = DB::table('lessons')->where('status', 'live')->find($id) ?? abort(404);
        $c = DB::table('courses')->where('status', 'live')->find($l->course_id) ?? abort(404);
        if (! $this->svc->canWatch($r->user()->id, $c, $l))
            return response()->json(['message' => 'To view this lesson, buy the course or take an Academy subscription.', 'plans' => Plan::active()->where('audience', 'training')->orderBy('price')->get(['id', 'name', 'period', 'price', 'currency']), 'price' => (float) $c->price], 402);
        return ['id' => $l->id, 'course_id' => $c->id, 'title' => $l->title, 'provider' => $l->provider, 'video_url' => $l->video_url, 'duration_sec' => $l->duration_sec, 'summary' => $l->summary, 'script' => $l->script,
            'percent' => (int) DB::table('lesson_progress')->where(['user_id' => $r->user()->id, 'lesson_id' => $id])->value('percent')];
    }

    public function progress(Request $r, int $id)
    {
        $d = $r->validate(['percent' => 'required|integer|min:0|max:100']);
        $l = DB::table('lessons')->where('status', 'live')->find($id) ?? abort(404);
        $c = DB::table('courses')->where('status', 'live')->find($l->course_id) ?? abort(404);
        abort_unless($this->svc->canWatch($r->user()->id, $c, $l), 402, 'Buy the course first.');
        return $this->svc->progress($r->user(), $l, $d['percent']);
    }

    public function mine(Request $r)
    {
        $uid = $r->user()->id;
        return DB::table('course_enrolments as e')->join('courses as c', 'c.id', '=', 'e.course_id')->where(['e.user_id' => $uid, 'e.status' => 'active'])->orderByDesc('e.id')->get(['c.*', 'e.completed_at'])
            ->map(fn ($c) => $this->course($c, $uid) + ['completed_at' => $c->completed_at])->values();
    }

    public function liveClasses(Request $r)
    {
        $sub = $this->svc->subscribed($r->user()->id);
        return DB::table('live_classes')->where('status', 'scheduled')->where('starts_at', '>=', now()->subMinutes(30))->orderBy('starts_at')->limit(50)->get(['id', 'title', 'segment', 'host', 'provider', 'starts_at', 'duration_min', 'subscribers_only', 'course_id'])
            ->map(fn ($c) => (array) $c + ['can_join' => ! $c->subscribers_only || $sub])->values();
    }

    public function joinLive(Request $r, int $id)
    {
        $c = DB::table('live_classes')->where('status', 'scheduled')->find($id) ?? abort(404);
        abort_if($c->subscribers_only && ! $this->svc->subscribed($r->user()->id), 402, 'This live class is for Academy subscribers.');
        abort_if(\Carbon\Carbon::parse($c->starts_at)->subMinutes(15)->isFuture(), 422, 'The join link opens 15 minutes before the class starts.');
        DB::table('live_class_joins')->insertOrIgnore(['class_id' => $id, 'user_id' => $r->user()->id, 'joined_at' => now()]);
        return ['join_url' => $c->join_url, 'provider' => $c->provider];
    }

    public function library(Request $r)
    {
        $r->validate(['segment' => 'nullable|in:school,college,business,family,general', 'q' => 'nullable|string|max:80', 'kind' => 'nullable|in:pdf,article,link,video']);
        return DB::table('library_items')->where('status', 'live')->when($r->query('segment'), fn ($q, $v) => $q->whereIn('segment', [$v, 'general']))->when($r->query('kind'), fn ($q, $v) => $q->where('kind', $v))
            ->when($r->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', $this->like($v))->orWhere('description', 'like', $this->like($v))->orWhere('category', 'like', $this->like($v))))
            ->orderByDesc('id')->limit(100)->get(['id', 'title', 'kind', 'segment', 'category', 'description', 'views']);
    }

    public function libraryOpen(Request $r, int $id)
    {
        $i = DB::table('library_items')->where('status', 'live')->find($id) ?? abort(404);
        DB::table('library_items')->where('id', $id)->increment('views');
        return ['id' => $i->id, 'title' => $i->title, 'kind' => $i->kind, 'url' => $i->url];
    }

    /** Instructor view: own courses, enrolments and revenue share. */
    public function earnings(Request $r)
    {
        $uid = $r->user()->id;
        $rows = DB::table('instructor_earnings as e')->join('courses as c', 'c.id', '=', 'e.course_id')->where('e.instructor_user_id', $uid)->orderByDesc('e.id')->limit(100)->get(['e.id', 'c.title', 'e.gross', 'e.share_pct', 'e.amount', 'e.status', 'e.created_at']);
        return ['pending' => (float) $rows->where('status', 'pending')->sum('amount'), 'paid' => (float) $rows->where('status', 'paid')->sum('amount'), 'courses' => DB::table('courses')->where('instructor_user_id', $uid)->get(['id', 'title', 'status', 'enrolments']), 'rows' => $rows];
    }
}
