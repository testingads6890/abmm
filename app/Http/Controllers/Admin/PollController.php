<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GeoTarget;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Polls (one question) and surveys (many questions) for everyone, a country, state, district, city or zone - with results and Excel export. */
class PollController extends Controller
{
    private function visible(Request $r)
    {
        $u = $r->user();
        $q = DB::table('polls');
        if (! $u->isSuper()) $q->whereIn('created_by', array_merge([$u->id], User::managedBy($u)->pluck('id')->all()));
        return $q;
    }

    private function find(Request $r, int $id): object
    {
        return $this->visible($r)->where('id', $id)->first() ?? abort(404);
    }

    public function index(Request $r)
    {
        $rows = $this->visible($r)->orderByDesc('id')->get();
        $counts = DB::table('poll_responses')->whereIn('poll_id', $rows->pluck('id'))->selectRaw('poll_id, COUNT(*) c')->groupBy('poll_id')->pluck('c', 'poll_id');
        return view('admin.engage.polls', ['rows' => $rows, 'counts' => $counts]);
    }

    public function edit(Request $r, ?int $id = null)
    {
        $p = $id ? $this->find($r, $id) : null;
        $qs = $p ? DB::table('poll_questions')->where('poll_id', $p->id)->orderBy('sort')->get() : collect();
        $text = $qs->map(fn ($q) => implode(' | ', [$q->type, $q->text, implode(',', json_decode($q->options ?: '[]', true)), $q->required ? 1 : 0]))->implode("\n");
        return view('admin.engage.poll-edit', ['p' => $p, 'target' => $p ? (json_decode($p->target ?: '[]', true) ?: []) : [], 'text' => $text, 'me' => $r->user()]);
    }

    /** Lines:  type | Question | option1,option2 | required(1/0)   types: single, multi, text, rating */
    public static function parseQuestions(string $text, string $kind): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 2 || ! in_array($p[0], ['single', 'multi', 'text', 'rating'], true) || $p[1] === '') continue;
            $opts = in_array($p[0], ['single', 'multi'], true) ? array_values(array_filter(array_map('trim', explode(',', $p[2] ?? '')))) : [];
            if (in_array($p[0], ['single', 'multi'], true) && count($opts) < 2) continue;
            $out[] = ['type' => $p[0], 'text' => mb_substr($p[1], 0, 300), 'options' => $opts, 'required' => ($p[3] ?? '1') !== '0'];
        }
        return $kind === 'poll' ? array_slice($out, 0, 1) : array_slice($out, 0, 40);
    }

    public function save(Request $r, ?int $id = null)
    {
        $d = $r->validate(['kind' => 'required|in:poll,survey', 'title' => 'required|string|max:180', 'description' => 'nullable|string|max:2000', 'questions_text' => 'required|string|max:20000',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at', 'country' => 'nullable|string|max:100', 'pradesh' => 'nullable|string|max:150', 'state' => 'nullable|string|max:150',
            'district' => 'nullable|string|max:150', 'city' => 'nullable|string|max:150', 'zone' => 'nullable|string|max:100']);
        $qs = self::parseQuestions($d['questions_text'], $d['kind']);
        abort_if(! $qs, 422, 'Write at least one valid question: type | Question | option1,option2 | required');
        $existing = $id ? $this->find($r, $id) : null;
        if ($existing && DB::table('poll_responses')->where('poll_id', $id)->exists()) abort(422, 'Answers already exist - the questions cannot be changed. Create a new poll.');
        $row = ['kind' => $d['kind'], 'title' => $d['title'], 'description' => $d['description'] ?? null, 'target' => json_encode(GeoTarget::enforce($r->user(), $r->only(GeoTarget::KEYS))),
            'starts_at' => $d['starts_at'] ?? null, 'ends_at' => $d['ends_at'] ?? null, 'show_results' => $r->boolean('show_results'), 'is_active' => $r->boolean('is_active'), 'updated_at' => now()];
        DB::transaction(function () use ($row, $qs, $id, $r) {
            if ($id) { DB::table('polls')->where('id', $id)->update($row); DB::table('poll_questions')->where('poll_id', $id)->delete(); $pid = $id; }
            else $pid = DB::table('polls')->insertGetId($row + ['created_by' => $r->user()->id, 'created_at' => now()]);
            foreach ($qs as $i => $q) DB::table('poll_questions')->insert(['poll_id' => $pid, 'sort' => $i, 'text' => $q['text'], 'type' => $q['type'], 'options' => json_encode($q['options']), 'required' => $q['required']]);
        });
        return redirect()->route('admin.polls')->with('ok', 'Saved.');
    }

    public function destroy(Request $r, int $id)
    {
        $this->find($r, $id);
        DB::table('polls')->where('id', $id)->delete();
        return back()->with('ok', 'Hata diya.');
    }

    private function aggregate(int $pollId): array
    {
        $qs = DB::table('poll_questions')->where('poll_id', $pollId)->orderBy('sort')->get();
        $resp = DB::table('poll_responses')->where('poll_id', $pollId)->get();
        $out = [];
        foreach ($qs as $q) {
            $opts = json_decode($q->options ?: '[]', true); $tally = array_fill_keys($opts, 0); $texts = []; $sum = 0; $n = 0; $dist = array_fill(1, 5, 0);
            foreach ($resp as $r) {
                $a = (json_decode($r->answers, true) ?: [])[$q->id] ?? null;
                if ($a === null || $a === '' || $a === []) continue;
                if ($q->type === 'single') { if (isset($tally[$a])) $tally[$a]++; }
                elseif ($q->type === 'multi') { foreach ((array) $a as $x) if (isset($tally[$x])) $tally[$x]++; }
                elseif ($q->type === 'rating') { $a = (int) $a; if ($a >= 1 && $a <= 5) { $sum += $a; $n++; $dist[$a]++; } }
                else $texts[] = (string) $a;
            }
            $out[] = ['q' => $q, 'tally' => $tally, 'texts' => $texts, 'avg' => $n ? round($sum / $n, 2) : null, 'dist' => $dist, 'answered' => $q->type === 'rating' ? $n : ($q->type === 'text' ? count($texts) : array_sum($tally))];
        }
        return [$out, $resp->count()];
    }

    public function results(Request $r, int $id)
    {
        $p = $this->find($r, $id);
        if ($r->export) return $this->export($r, $p);
        [$agg, $total] = $this->aggregate($id);
        $byState = DB::table('poll_responses')->where('poll_id', $id)->selectRaw('country, state, COUNT(*) c')->groupBy('country', 'state')->orderByDesc('c')->get();
        return view('admin.engage.poll-results', ['p' => $p, 'agg' => $agg, 'total' => $total, 'byState' => $byState]);
    }

    private function export(Request $r, object $p)
    {
        abort_unless(in_array($r->export, ['xlsx', 'csv', 'pdf'], true), 404);
        $qs = DB::table('poll_questions')->where('poll_id', $p->id)->orderBy('sort')->get();
        $head = array_merge(['Name', 'Mobile', 'Country', 'State', 'District', 'City', 'Submitted'], $qs->map(fn ($q) => $q->text)->all());
        $rows = (function () use ($p, $qs) {
            foreach (DB::table('poll_responses as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.poll_id', $p->id)->orderBy('r.id')->select('r.*', 'u.name', 'u.mobile')->cursor() as $x) {
                $a = json_decode($x->answers, true) ?: [];
                yield array_merge([$x->name, $x->mobile, $x->country, $x->state, $x->district, $x->city, $x->created_at], $qs->map(fn ($q) => is_array($a[$q->id] ?? null) ? implode(', ', $a[$q->id]) : (string) ($a[$q->id] ?? ''))->all());
            }
        })();
        return Exporter::download($r->export, 'poll-' . $p->id, $p->title, $head, $rows);
    }
}
