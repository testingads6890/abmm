<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\ExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app (Milestone F): weekly MCQ exams, instant score with explanations, weekly / monthly ranks. */
class ExamController extends Controller
{
    public function __construct(private ExamService $svc) {}

    private function exam(int $id): object { return DB::table('exams')->whereIn('status', ['live', 'closed'])->find($id) ?? abort(404); }

    public function profile(Request $r)
    {
        return ['segments' => ExamService::SEGMENTS, 'profile' => DB::table('learner_profiles')->where('user_id', $r->user()->id)->first(), 'segment' => $this->svc->segmentOf($r->user()->id)];
    }

    public function saveProfile(Request $r)
    {
        $d = $r->validate(['segment' => 'required|in:school,college,business,family', 'class_name' => 'nullable|string|max:60', 'institute' => 'nullable|string|max:150']);
        $this->svc->setProfile($r->user(), $d);
        return ['ok' => true];
    }

    public function index(Request $r)
    {
        $uid = $r->user()->id;
        return $this->svc->live($uid)->map(function ($e) use ($uid) {
            $at = DB::table('exam_attempts')->where(['exam_id' => $e->id, 'user_id' => $uid])->first();
            return ['id' => $e->id, 'title' => $e->title, 'segment' => $e->segment, 'ends_at' => $e->ends_at, 'duration_min' => $e->duration_min, 'questions' => count(json_decode($e->question_ids ?: '[]', true)), 'pass_pct' => $e->pass_pct,
                'state' => ! $at ? 'not_started' : ($at->submitted_at ? 'submitted' : 'in_progress'), 'percent' => $at?->percent !== null ? (float) $at->percent : null];
        })->values();
    }

    public function start(Request $r, int $id) { return $this->svc->start($r->user(), $this->exam($id)); }

    public function submit(Request $r, int $id)
    {
        $d = $r->validate(['answers' => 'required|array|max:100', 'answers.*' => 'nullable|integer|min:0|max:9']);
        return $this->svc->submit($r->user(), $this->exam($id), $d['answers']);
    }

    /** Review of my own submitted attempt (answers + explanations). */
    public function result(Request $r, int $id)
    {
        $e = $this->exam($id); $at = DB::table('exam_attempts')->where(['exam_id' => $id, 'user_id' => $r->user()->id])->whereNotNull('submitted_at')->first() ?? abort(404, 'You did not take this exam.');
        $ans = json_decode($at->answers ?: '[]', true); $review = [];
        foreach ($this->svc->questions($e, true) as $q) { $g = isset($ans[$q['id']]) ? (int) $ans[$q['id']] : null; $review[] = $q + ['your_answer' => $g, 'is_correct' => $g === $q['correct']]; }
        return ['score' => $at->score, 'total' => $at->total, 'percent' => (float) $at->percent, 'rank' => $at->rank, 'passed' => $at->percent >= $e->pass_pct, 'review' => $review];
    }

    public function leaderboard(Request $r, int $id)
    {
        $e = $this->exam($id); $uid = $r->user()->id;
        $b = $this->svc->board($e, 100);
        return ['exam' => $e->title, 'final' => $e->status === 'closed', 'top' => array_map(fn ($x) => array_diff_key($x, ['user_id' => 1]) + ['me' => $x['user_id'] === $uid], array_slice($b, 0, 20)), 'my_rank' => collect($b)->firstWhere('user_id', $uid)['rank'] ?? null];
    }

    public function monthly(Request $r)
    {
        $d = $r->validate(['segment' => 'nullable|in:school,college,business,family', 'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        $seg = $d['segment'] ?? $this->svc->segmentOf($r->user()->id);
        $b = $this->svc->monthBoard($seg, $d['month'] ?? null);
        return ['segment' => $seg, 'month' => $d['month'] ?? now()->format('Y-m'), 'top' => array_map(fn ($x) => array_diff_key($x, ['user_id' => 1]) + ['me' => $x['user_id'] === $r->user()->id], array_slice($b, 0, 20)), 'my_rank' => collect($b)->firstWhere('user_id', $r->user()->id)['rank'] ?? null];
    }

    public function history(Request $r)
    {
        return DB::table('exam_attempts as a')->join('exams as e', 'e.id', '=', 'a.exam_id')->where('a.user_id', $r->user()->id)->whereNotNull('a.submitted_at')->orderByDesc('a.id')->limit(50)->get(['e.id', 'e.title', 'a.score', 'a.total', 'a.percent', 'a.rank', 'a.submitted_at']);
    }
}
