<?php

namespace App\Services;

use App\Models\{User, UserNotification};
use App\Support\Area;
use Illuminate\Support\Facades\DB;

/**
 * Milestone F - weekly MCQ exams per segment (school, college, business, family): question bank, auto weekly exam, timed attempt,
 * instant score with explanations, weekly + monthly ranks, e-certificates for passers and toppers.
 */
class ExamService
{
    public const SEGMENTS = ['school' => 'School students', 'college' => 'College students', 'business' => 'Business people', 'family' => 'Whole family'];
    public const GRACE_SEC = 45;

    public function questionCount(): int { return (int) config('abmm.exam.questions', 10); }

    private function tell(?int $uid, string $title, string $body = ''): void
    {
        if ($uid) UserNotification::create(['user_id' => $uid, 'title' => trim($title . ($body ? ": $body" : ''))]);
    }

    public function segmentOf(int $userId): string { return (string) (DB::table('learner_profiles')->where('user_id', $userId)->value('segment') ?: 'family'); }

    /** A member may take exams of his own segment and the "family" exam (open to everybody). */
    public function eligible(int $userId, object $exam): bool { return $exam->segment === 'family' || $exam->segment === $this->segmentOf($userId); }

    public function setProfile(User $u, array $d): void
    {
        DB::table('learner_profiles')->updateOrInsert(['user_id' => $u->id], $d + Area::of($u) + ['updated_at' => now(), 'created_at' => now()]);
    }

    /** Creates this week's exam of each segment (Mon 00:00 - Sun 23:59) from the active question bank. Returns the number created. */
    public function ensureWeekly(): int
    {
        $start = now()->startOfWeek(); $end = now()->endOfWeek(); $n = 0;
        foreach (self::SEGMENTS as $seg => $label) {
            if (DB::table('exams')->where(['segment' => $seg, 'kind' => 'weekly'])->where('starts_at', '>=', $start)->where('starts_at', '<=', $end)->exists()) continue;
            $ids = DB::table('exam_questions')->where(['segment' => $seg, 'is_active' => true])->inRandomOrder()->limit($this->questionCount())->pluck('id');
            if ($ids->count() < min(5, $this->questionCount())) continue;               // bank too small - editors must add questions
            DB::table('exams')->insert(['title' => "Weekly quiz - $label (" . $start->format('d M') . ')', 'segment' => $seg, 'kind' => 'weekly', 'starts_at' => $start, 'ends_at' => $end, 'duration_min' => max(10, $ids->count() * 2),
                'pass_pct' => 40, 'question_ids' => json_encode($ids->values()), 'status' => 'live', 'created_at' => now(), 'updated_at' => now()]); $n++;
        }
        return $n;
    }

    public function live(int $userId)
    {
        return DB::table('exams')->where('status', 'live')->where('starts_at', '<=', now())->where('ends_at', '>=', now())->orderBy('ends_at')->get()->filter(fn ($e) => $this->eligible($userId, $e))->values();
    }

    public function questions(object $exam, bool $withAnswers = false): array
    {
        $ids = json_decode($exam->question_ids ?: '[]', true) ?: [];
        $rows = DB::table('exam_questions')->whereIn('id', $ids)->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) if ($q = $rows[$id] ?? null) $out[] = ['id' => $q->id, 'subject' => $q->subject, 'question' => $q->question, 'options' => json_decode($q->options, true)] + ($withAnswers ? ['correct' => (int) $q->correct, 'explanation' => $q->explanation] : []);
        return $out;
    }

    public function start(User $u, object $exam): array
    {
        abort_unless($exam->status === 'live' && $exam->starts_at <= now()->toDateTimeString() && $exam->ends_at >= now()->toDateTimeString(), 422, 'This exam is not open yet.');
        abort_unless($this->eligible($u->id, $exam), 403, 'This exam is not for your segment. Change the segment in your learner profile.');
        $at = DB::table('exam_attempts')->where(['exam_id' => $exam->id, 'user_id' => $u->id])->first();
        abort_if($at && $at->submitted_at, 422, 'You have already taken this exam.');
        if (! $at) {
            $a = Area::of($u);
            DB::table('exam_attempts')->insert(['exam_id' => $exam->id, 'user_id' => $u->id, 'family_id' => $a['family_id'], 'started_at' => now(), 'country' => $a['country'], 'pradesh' => $a['pradesh'], 'district' => $a['district'], 'city' => $a['city'], 'zone' => $a['zone'], 'created_at' => now(), 'updated_at' => now()]);
            $at = DB::table('exam_attempts')->where(['exam_id' => $exam->id, 'user_id' => $u->id])->first();
        }
        $left = max(0, $exam->duration_min * 60 - now()->diffInSeconds(\Carbon\Carbon::parse($at->started_at), true));
        return ['attempt_id' => $at->id, 'seconds_left' => (int) $left, 'questions' => $this->questions($exam)];
    }

    public function submit(User $u, object $exam, array $answers): array
    {
        $at = DB::table('exam_attempts')->where(['exam_id' => $exam->id, 'user_id' => $u->id])->first() ?? abort(422, 'Start the exam first.');
        abort_if($at->submitted_at, 422, 'You have already taken this exam.');
        $secs = (int) \Carbon\Carbon::parse($at->started_at)->diffInSeconds(now(), true);
        abort_if($secs > $exam->duration_min * 60 + self::GRACE_SEC, 422, 'Time is up.');
        $qs = $this->questions($exam, true); $score = 0; $review = [];
        foreach ($qs as $q) {
            $given = isset($answers[$q['id']]) ? (int) $answers[$q['id']] : null;
            $right = $given === $q['correct']; if ($right) $score++;
            $review[] = ['id' => $q['id'], 'question' => $q['question'], 'options' => $q['options'], 'your_answer' => $given, 'correct' => $q['correct'], 'is_correct' => $right, 'explanation' => $q['explanation']];
        }
        $total = count($qs); $pct = $total ? round($score * 100 / $total, 2) : 0;
        DB::table('exam_attempts')->where('id', $at->id)->update(['submitted_at' => now(), 'answers' => json_encode($answers), 'score' => $score, 'total' => $total, 'percent' => $pct, 'seconds' => $secs, 'updated_at' => now()]);
        $passed = $pct >= $exam->pass_pct;
        $pts = app(PointsService::class);
        $pts->award($u, 'quiz_complete', 'exam' . $exam->id);
        if ($passed) $pts->award($u, 'exam_pass', (string) $exam->id);
        return ['score' => $score, 'total' => $total, 'percent' => (float) $pct, 'passed' => $passed, 'pass_pct' => $exam->pass_pct, 'seconds' => $secs, 'review' => $review, 'rank_after_close' => 'The rank is available after the exam closes.'];
    }

    private function shortName(int $uid): string
    {
        $p = preg_split('/\s+/', trim((string) DB::table('users')->where('id', $uid)->value('name'))) ?: [];
        return ($p[0] ?? 'Member') . (isset($p[1]) ? ' ' . mb_substr($p[1], 0, 1) . '.' : '');
    }

    public function board(object $exam, int $limit = 50): array
    {
        return DB::table('exam_attempts')->where('exam_id', $exam->id)->whereNotNull('submitted_at')->orderByDesc('percent')->orderBy('seconds')->orderBy('id')->limit($limit)->get()
            ->map(fn ($a, $i) => ['rank' => $i + 1, 'name' => $this->shortName($a->user_id), 'city' => $a->city, 'percent' => (float) $a->percent, 'score' => $a->score, 'total' => $a->total, 'seconds' => $a->seconds, 'user_id' => $a->user_id])->all();
    }

    /** Monthly rank: average percent over the month's attempts of the segment (min 2 exams), ties by time. */
    public function monthBoard(string $segment, ?string $month = null, int $limit = 50): array
    {
        $m = $month ?: now()->format('Y-m'); $from = \Carbon\Carbon::parse("$m-01")->startOfMonth(); $to = $from->copy()->endOfMonth();
        $rows = DB::table('exam_attempts as a')->join('exams as e', 'e.id', '=', 'a.exam_id')->where('e.segment', $segment)->whereNotNull('a.submitted_at')->whereBetween('e.starts_at', [$from, $to])
            ->groupBy('a.user_id')->selectRaw('a.user_id, count(*) as exams, avg(a.percent) as avg_pct, sum(a.seconds) as secs')->get()
            ->filter(fn ($r) => $r->exams >= 2)->sortBy([['avg_pct', 'desc'], ['secs', 'asc']])->values()->take($limit);
        return $rows->map(fn ($r, $i) => ['rank' => $i + 1, 'name' => $this->shortName($r->user_id), 'exams' => (int) $r->exams, 'avg_percent' => round((float) $r->avg_pct, 2), 'user_id' => $r->user_id])->all();
    }

    /** Closes exams whose time is over: ranks, notifications, certificates (passers + top 3). Returns number of exams closed. */
    public function closeDue(): int
    {
        $n = 0; $cert = app(CertificateService::class);
        foreach (DB::table('exams')->where('status', 'live')->where('ends_at', '<', now())->get() as $e) {
            DB::table('exams')->where('id', $e->id)->update(['status' => 'closed', 'results_sent' => true, 'updated_at' => now()]);
            foreach ($this->board($e, 100000) as $r) {
                DB::table('exam_attempts')->where(['exam_id' => $e->id, 'user_id' => $r['user_id']])->update(['rank' => $r['rank']]);
                $this->tell($r['user_id'], 'Weekly quiz result', "{$e->title}: {$r['percent']}% - Rank #{$r['rank']}");
                $uid = $r['user_id']; $fid = DB::table('users')->where('id', $uid)->value('family_id');
                try {
                    if ($r['rank'] <= 3) $cert->issue($uid, $fid, 'exam_rank', "Rank #{$r['rank']} - {$e->title}", "Secured rank {$r['rank']} with {$r['percent']}% in the weekly quiz.", "exam{$e->id}");
                    elseif ($r['percent'] >= $e->pass_pct) $cert->issue($uid, $fid, 'exam', "Passed - {$e->title}", "Scored {$r['percent']}% in the weekly quiz.", "exam{$e->id}");
                } catch (\Throwable $x) { report($x); }
            }
            $n++;
        }
        return $n;
    }

    /** Admin: end an exam right now and publish ranks / certificates. */
    public function forceClose(int $examId): void
    {
        DB::table('exams')->where(['id' => $examId, 'status' => 'live'])->update(['ends_at' => now()->subMinute()]);
        $this->closeDue();
    }

    /** On/after the 1st: monthly toppers of the previous month get a certificate. */
    public function monthlyAwards(): int
    {
        $prev = now()->subMonthNoOverflow()->format('Y-m'); $n = 0; $cert = app(CertificateService::class);
        foreach (array_keys(self::SEGMENTS) as $seg) foreach (array_slice($this->monthBoard($seg, $prev), 0, 3) as $r) {
            $fid = DB::table('users')->where('id', $r['user_id'])->value('family_id');
            try { if ($cert->issue($r['user_id'], $fid, 'exam_month', "Monthly Rank #{$r['rank']} - " . self::SEGMENTS[$seg], "Average {$r['avg_percent']}% across {$r['exams']} weekly quizzes in " . \Carbon\Carbon::parse("$prev-01")->format('F Y') . '.', "$prev-$seg")) $n++; } catch (\Throwable $x) { report($x); }
        }
        return $n;
    }
}
