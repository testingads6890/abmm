<?php

namespace App\Services;

use App\Models\{User, UserNotification};
use App\Services\Payments\PaymentLedger;
use App\Support\Area;
use Illuminate\Support\Facades\DB;

/**
 * Milestone F - Learning Academy: courses of short videos (free previews, free courses, paid courses, "training" subscription),
 * instructor revenue share, progress tracking, completion certificates, live classes and a knowledge library.
 */
class AcademyService
{
    public const SEGMENTS = ['school' => 'School students', 'college' => 'College students', 'business' => 'Business people', 'family' => 'Whole family', 'general' => 'Everyone'];

    public function __construct(private PaymentLedger $ledger, private SubscriptionService $subs) {}

    private function tell(?int $uid, string $title, string $body = ''): void
    {
        if (! $uid) return;
        UserNotification::create(['user_id' => $uid, 'title' => trim($title . ($body ? ": $body" : ''))]);
    }

    public function subscribed(int $userId): bool { return $this->subs->hasLive('training', $userId); }

    public function enrolment(int $userId, int $courseId): ?object
    {
        return DB::table('course_enrolments')->where(['user_id' => $userId, 'course_id' => $courseId, 'status' => 'active'])->first();
    }

    /** Can this user watch the full course? */
    public function hasAccess(int $userId, object $c): bool
    {
        return $c->free || $this->enrolment($userId, $c->id) || ($c->subscription && $this->subscribed($userId));
    }

    public function canWatch(int $userId, object $course, object $lesson): bool { return $lesson->free_preview || $this->hasAccess($userId, $course); }

    /** Free / subscribed users are enrolled at once; otherwise a payment is created. Returns [enrolment id, payment|null]. */
    public function enrol(User $u, object $c, ?string $coupon = null): array
    {
        abort_unless($c->status === 'live', 404);
        if ($e = $this->enrolment($u->id, $c->id)) return [$e->id, null];
        if ($c->free || ($c->subscription && $this->subscribed($u->id))) {
            $id = $this->upsertEnrolment($c->id, $u->id, $c->free ? 'free' : 'subscription', 'active');
            DB::table('courses')->where('id', $c->id)->increment('enrolments');
            return [$id, null];
        }
        abort_if($c->price <= 0, 402, 'An Academy subscription is required for this course.');
        $id = $this->upsertEnrolment($c->id, $u->id, 'purchase', 'awaiting_payment');
        $a = Area::of($u); $f = $a['family_id'] ? \App\Models\Family::withoutGlobalScopes()->find($a['family_id']) : null;
        $p = $this->ledger->create(['purpose' => 'course', 'purpose_ref' => (string) $id, 'amount' => $c->price, 'currency' => $c->currency, 'country' => $a['country'], 'city' => $a['city'], 'user_id' => $u->id, 'family_id' => $a['family_id'],
            'payer_name' => $f?->head_name ?: $u->name, 'payer_email' => $f?->email, 'payer_mobile' => $f ? PhoneService::e164($f->mobile, $f->country_code ?: '+91') : null, 'coupon' => $coupon, 'audience' => 'member', 'meta' => ['title' => 'Course: ' . $c->title]]);
        DB::table('course_enrolments')->where('id', $id)->update(['payment_id' => $p->id]);
        return [$id, $p];
    }

    private function upsertEnrolment(int $courseId, int $userId, string $source, string $status): int
    {
        $ex = DB::table('course_enrolments')->where(['course_id' => $courseId, 'user_id' => $userId])->first();
        if ($ex) { DB::table('course_enrolments')->where('id', $ex->id)->update(['source' => $source, 'status' => $status, 'updated_at' => now()]); return $ex->id; }
        return DB::table('course_enrolments')->insertGetId(['course_id' => $courseId, 'user_id' => $userId, 'source' => $source, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Ledger settle hook. Books the instructor's share. */
    public function paid(int $enrolId): void
    {
        $e = DB::table('course_enrolments')->find($enrolId); if (! $e || $e->status === 'active') return;
        DB::table('course_enrolments')->where('id', $enrolId)->update(['status' => 'active', 'updated_at' => now()]);
        $c = DB::table('courses')->find($e->course_id); if (! $c) return;
        DB::table('courses')->where('id', $c->id)->increment('enrolments');
        $pay = $e->payment_id ? DB::table('payments')->find($e->payment_id) : null;
        if ($pay && $c->instructor_user_id && $c->instructor_share_pct > 0) {
            $net = (float) $pay->amount - (float) $pay->tax_amount;          // share is on the amount without tax
            DB::table('instructor_earnings')->insertOrIgnore(['course_id' => $c->id, 'instructor_user_id' => $c->instructor_user_id, 'payment_id' => $pay->id, 'gross' => $net, 'share_pct' => $c->instructor_share_pct,
                'amount' => round($net * $c->instructor_share_pct / 100, 2), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->tell($c->instructor_user_id, 'Course sale', $c->title);
        }
        $this->tell($e->user_id, 'Enrolled in the course', $c->title);
    }

    public function refunded(int $enrolId): void
    {
        $e = DB::table('course_enrolments')->find($enrolId); if (! $e) return;
        DB::table('course_enrolments')->where('id', $enrolId)->update(['status' => 'cancelled', 'updated_at' => now()]);
        if ($e->payment_id) DB::table('instructor_earnings')->where('payment_id', $e->payment_id)->where('status', 'pending')->update(['status' => 'reversed', 'updated_at' => now()]);
    }

    /** Video progress; >= 90% completes the lesson, all lessons done completes the course (points + certificate). */
    public function progress(User $u, object $lesson, int $percent): array
    {
        $percent = max(0, min(100, $percent));
        $row = DB::table('lesson_progress')->where(['user_id' => $u->id, 'lesson_id' => $lesson->id])->first();
        $newly = false;
        if (! $row) { DB::table('lesson_progress')->insert(['user_id' => $u->id, 'lesson_id' => $lesson->id, 'course_id' => $lesson->course_id, 'percent' => $percent, 'completed_at' => $percent >= 90 ? now() : null, 'created_at' => now(), 'updated_at' => now()]); $newly = $percent >= 90; }
        else {
            $upd = ['percent' => max($row->percent, $percent), 'updated_at' => now()];
            if (! $row->completed_at && $percent >= 90) { $upd['completed_at'] = now(); $newly = true; }
            DB::table('lesson_progress')->where('id', $row->id)->update($upd);
        }
        $done = false;
        if ($newly) {
            app(PointsService::class)->award($u, 'lesson_done', (string) $lesson->id);
            $done = $this->completeIfFinished($u, $lesson->course_id);
        }
        return ['lesson_completed' => (bool) ($newly || ($row && $row->completed_at)), 'course_completed' => $done, 'course' => $this->courseProgress($u->id, $lesson->course_id)];
    }

    public function courseProgress(int $userId, int $courseId): array
    {
        $total = DB::table('lessons')->where(['course_id' => $courseId, 'status' => 'live'])->count();
        $done = DB::table('lesson_progress as p')->join('lessons as l', 'l.id', '=', 'p.lesson_id')->where(['p.user_id' => $userId, 'p.course_id' => $courseId, 'l.status' => 'live'])->whereNotNull('p.completed_at')->count();
        return ['lessons' => $total, 'completed' => $done, 'percent' => $total ? (int) round($done * 100 / $total) : 0];
    }

    private function completeIfFinished(User $u, int $courseId): bool
    {
        $p = $this->courseProgress($u->id, $courseId);
        if (! $p['lessons'] || $p['completed'] < $p['lessons']) return false;
        $e = DB::table('course_enrolments')->where(['user_id' => $u->id, 'course_id' => $courseId])->first();
        if ($e && $e->completed_at) return false;
        if ($e) DB::table('course_enrolments')->where('id', $e->id)->update(['completed_at' => now(), 'updated_at' => now()]);
        else DB::table('course_enrolments')->insert(['course_id' => $courseId, 'user_id' => $u->id, 'source' => 'free', 'status' => 'active', 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $c = DB::table('courses')->find($courseId);
        app(PointsService::class)->award($u, 'course_done', (string) $courseId);
        try { app(CertificateService::class)->issue($u->id, $u->family_id, 'course', 'Course completed: ' . $c->title, 'Successfully completed all lessons of the course.', (string) $courseId); } catch (\Throwable $e2) { report($e2); }
        return true;
    }

    public function remindClasses(): int
    {
        $n = 0;
        foreach (DB::table('live_classes')->where('status', 'scheduled')->where('reminded', false)->whereBetween('starts_at', [now(), now()->addDay()])->get() as $c) {
            $ids = DB::table('course_enrolments')->where(['course_id' => $c->course_id, 'status' => 'active'])->pluck('user_id');
            $ids = $ids->merge(DB::table('learner_profiles')->when($c->segment !== 'general', fn ($q) => $q->where('segment', $c->segment))->pluck('user_id'))->unique();
            foreach ($ids as $uid) $this->tell($uid, 'Live class kal/aaj', $c->title . ' - ' . \Carbon\Carbon::parse($c->starts_at)->format('d M, h:i A'));
            DB::table('live_classes')->where('id', $c->id)->update(['reminded' => true]); $n++;
        }
        return $n;
    }

    /** Admin: AI (or skeleton) course draft with lessons and MCQs; everything stays draft / inactive until an editor reviews it. */
    public function aiDraft(string $topic, string $segment, int $lessons, ?int $instructorUserId = null): int
    {
        $d = app(AiService::class)->draftCourse($topic, $segment, $lessons);
        $cid = DB::table('courses')->insertGetId(['title' => $topic, 'segment' => $segment, 'description' => $d['description'] ?? null, 'instructor_user_id' => $instructorUserId, 'ai_generated' => true, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        foreach ($d['lessons'] as $i => $l) DB::table('lessons')->insert(['course_id' => $cid, 'title' => $l['title'], 'provider' => 'ai', 'summary' => $l['summary'] ?? null, 'script' => $l['script'] ?? null, 'sort' => $i + 1, 'status' => 'live', 'free_preview' => $i === 0, 'created_at' => now(), 'updated_at' => now()]);
        $examSeg = in_array($segment, ['school', 'college', 'business', 'family'], true) ? $segment : 'family';
        foreach ($d['questions'] ?? [] as $q) DB::table('exam_questions')->insert(['segment' => $examSeg, 'subject' => $topic, 'question' => $q['question'], 'options' => json_encode(array_values($q['options'])), 'correct' => (int) $q['correct'], 'explanation' => $q['explanation'] ?? null,
            'difficulty' => 'medium', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
        return $cid;
    }
}
