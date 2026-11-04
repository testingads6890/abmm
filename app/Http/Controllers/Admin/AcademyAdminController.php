<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\{AcademyService, AiService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin (Milestone F): AI course drafting and bulk import of exam questions. */
class AcademyAdminController extends Controller
{
    public function ai(Request $r)
    {
        abort_unless($r->user()->can('academy.create'), 403);
        return view('admin.academy.ai', ['segments' => AcademyService::SEGMENTS + ['general' => 'General'], 'recent' => DB::table('courses')->where('ai_generated', true)->orderByDesc('id')->limit(10)->get(['id', 'title', 'status']), 'aiOn' => app(AiService::class)->enabled()]);
    }

    public function aiStore(Request $r, AcademyService $svc)
    {
        abort_unless($r->user()->can('academy.create'), 403);
        $d = $r->validate(['topic' => 'required|string|max:150', 'segment' => 'required|in:school,college,business,family,general', 'lessons' => 'required|integer|min:2|max:12', 'instructor_user_id' => 'nullable|integer|exists:users,id']);
        $id = $svc->aiDraft($d['topic'], $d['segment'], $d['lessons'], $d['instructor_user_id'] ?? null);
        return redirect()->route('admin.manage.edit', ['courses', $id])->with('ok', 'The AI draft is ready (Draft status). Add video links, check the scripts / questions and then Publish.');
    }

    public function questionImport(Request $r)
    {
        abort_unless($r->user()->can('exams.create'), 403);
        return view('admin.academy.import', ['done' => session('done')]);
    }

    /** One question per line: segment | subject | question | option1;option2;option3;option4 | correct number (1 = first) | explanation */
    public function questionImportStore(Request $r)
    {
        abort_unless($r->user()->can('exams.create'), 403);
        $r->validate(['rows' => 'required|string|max:200000']);
        $ok = 0; $bad = [];
        foreach (preg_split('/\R/', $r->input('rows')) as $n => $line) {
            if (trim($line) === '') continue;
            $c = array_map('trim', explode('|', $line));
            $opts = array_values(array_filter(array_map('trim', explode(';', $c[3] ?? '')), fn ($x) => $x !== ''));
            $no = (int) ($c[4] ?? 0);
            if (! in_array($c[0] ?? '', ['school', 'college', 'business', 'family'], true) || ($c[2] ?? '') === '' || count($opts) < 2 || count($opts) > 6 || $no < 1 || $no > count($opts)) { $bad[] = $n + 1; continue; }
            DB::table('exam_questions')->insert(['segment' => $c[0], 'subject' => $c[1] ?: null, 'question' => $c[2], 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE), 'correct' => $no - 1, 'explanation' => $c[5] ?? null, 'difficulty' => 'medium', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $ok++;
        }
        return redirect()->route('admin.academy.import')->with('done', ['ok' => $ok, 'bad' => $bad]);
    }
}
