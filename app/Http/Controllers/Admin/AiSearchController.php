<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Family, JobPost, Member, MatrimonyProfile};
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin: one smart keyword box across modules ("CA ladka Indore 28 saal", "Rathi Pune", "9811..."), limited to the admin's own area and permissions. */
class AiSearchController extends Controller
{
    private const MODULES = ['families' => ['Families', 'families.view'], 'members' => ['Members', 'families.view'], 'matrimony' => ['Matrimony', 'matrimony.view'], 'business' => ['Businesses', 'business.view'],
        'jobs' => ['Jobs', 'jobs.view'], 'partners' => ['Partners (bureau / organiser / recruiter)', 'partners.view'], 'vendors' => ['Event vendors', 'vendors.view'], 'events' => ['Events', 'events.view']];

    private static function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    private function kw($q, array $cols, array $exp)
    {
        foreach ($exp as $alts) $q->where(function ($w) use ($alts, $cols) { foreach ($alts as $a) foreach ($cols as $c) $w->orWhere($c, 'like', self::like($a)); });
        return $q;
    }

    public function index(Request $r, AiService $ai)
    {
        $allowed = array_filter(self::MODULES, fn ($m) => $r->user()->can($m[1]));
        $q = trim((string) $r->query('q')); $mod = $r->query('module', 'all');
        $res = []; $f = [];
        if ($q !== '' && mb_strlen($q) >= 2) {
            $exp = $ai->expand(AiService::tokens($q)) ?: [$q => [$q]];
            $digits = preg_replace('/\D/', '', $q);
            $run = fn (string $m) => $mod === 'all' || $mod === $m;
            if (isset($allowed['families']) && $run('families')) {
                $rows = Family::query()->where(fn ($w) => $w->where('family_code', $q)->when(strlen($digits) >= 6, fn ($x) => $x->orWhere('mobile', 'like', "%$digits%")))->limit(30)->get();
                if ($rows->isEmpty()) $rows = $this->kw(Family::query(), ['head_name', 'surname', 'city', 'district', 'family_code', 'profession', 'gotra'], $exp)->limit(30)->get();
                foreach ($rows as $x) $res[] = ['module' => 'Family', 'title' => $x->head_name . ' (' . $x->family_code . ')', 'sub' => trim($x->city . ', ' . $x->pradesh . ' · ' . $x->mobile), 'url' => route('admin.families.show', $x->id)];
            }
            if (isset($allowed['members']) && $run('members')) foreach ($this->kw(Member::query(), ['name', 'surname', 'occupation', 'profession', 'city', 'gotra'], $exp)->limit(30)->get() as $x)
                $res[] = ['module' => 'Member', 'title' => $x->name . ' ' . $x->surname, 'sub' => trim($x->relation . ' · ' . $x->city . ' · ' . $x->occupation), 'url' => route('admin.families.show', $x->family_id)];
            if (isset($allowed['matrimony']) && $run('matrimony')) {
                $f = $ai->understand($q, 'matrimony', MatrimonyProfile::withoutGlobalScopes()->select('city')->distinct()->limit(500)->pluck('city')->filter()->map(fn ($c) => mb_strtolower($c))->all());
                $m = MatrimonyProfile::query();
                if (isset($f['gender'])) $m->where('gender', $f['gender']);
                if (isset($f['age_min'])) $m->where('dob', '<=', now()->subYears((int) $f['age_min'])->toDateString());
                if (isset($f['age_max'])) $m->where('dob', '>=', now()->subYears((int) $f['age_max'] + 1)->addDay()->toDateString());
                if (isset($f['income_min'])) $m->where('annual_income', '>=', (int) $f['income_min']);
                if (isset($f['height_min'])) $m->where('height_cm', '>=', (int) $f['height_min']);
                if (isset($f['diet'])) $m->where('diet', 'like', self::like($f['diet']));
                if (($f['manglik'] ?? null) === 'yes') $m->where('manglik', true); if (($f['manglik'] ?? null) === 'no') $m->where('manglik', false);
                if (! empty($f['places'])) $m->where(fn ($w) => $w->whereIn(DB::raw('LOWER(city)'), $f['places'])->orWhereIn(DB::raw('LOWER(state)'), $f['places']));
                if (! empty($f['keywords'])) $this->kw($m, ['name', 'qualification', 'degree', 'occupation', 'parinay_id', 'about', 'gotra', 'mobile'], $ai->expand($f['keywords']));
                foreach ($m->orderByDesc('id')->limit(30)->get() as $x) $res[] = ['module' => 'Matrimony', 'title' => $x->name . ' (' . $x->parinay_id . ')', 'sub' => trim($x->gender . ', ' . $x->dob?->age . ' yrs · ' . $x->qualification . ' · ' . $x->city), 'url' => route('admin.matrimony.index', ['q' => $x->parinay_id])];
            }
            if (isset($allowed['business']) && $run('business')) foreach ($this->kw(DB::table('businesses'), ['name', 'type', 'description', 'city', 'contact_person'], $exp)->tap(fn ($x) => \App\Http\Controllers\Admin\ManageController::scopeArea($x, 'businesses'))->limit(30)->get() as $x)
                $res[] = ['module' => 'Business', 'title' => $x->name, 'sub' => trim($x->type . ' · ' . $x->city . ' · ' . $x->mobile), 'url' => route('admin.manage', 'sellers') . '?q=' . urlencode($x->name)];
            if (isset($allowed['jobs']) && $run('jobs')) foreach ($this->kw(JobPost::query(), ['title', 'company', 'skills', 'category', 'city', 'description'], $exp)->limit(30)->get() as $x)
                $res[] = ['module' => 'Job', 'title' => $x->title . ' — ' . $x->company, 'sub' => trim($x->city . ' · ' . $x->status), 'url' => route('admin.jobs.show', $x->id)];
            foreach (['partners' => ['partners', ['name', 'description', 'city', 'contact_person', 'phone']], 'vendors' => ['event_vendors', ['name', 'category', 'description', 'city', 'phone']]] as $k => [$tbl, $cols]) {
                if (! isset($allowed[$k]) || ! $run($k)) continue;
                $qq = \App\Http\Controllers\Admin\ManageController::scopeArea($this->kw(DB::table($tbl), $cols, $exp), $tbl);
                foreach ($qq->limit(30)->get() as $x) $res[] = ['module' => $k === 'partners' ? 'Partner' : 'Vendor', 'title' => $x->name, 'sub' => trim(($x->kind ?? $x->category ?? '') . ' · ' . $x->city . ' · ' . $x->status),
                    'url' => route('admin.manage', $k === 'partners' ? ['bureau' => 'bureaus', 'organiser' => 'organisers', 'recruiter' => 'recruiters'][$x->kind] : 'vendors') . '?q=' . urlencode($x->name)];
            }
            if (isset($allowed['events']) && $run('events')) foreach ($this->kw(\App\Models\Event::query(), ['title', 'description', 'venue'], $exp)->orderByDesc('event_date')->limit(30)->get() as $x)
                $res[] = ['module' => 'Event', 'title' => $x->title, 'sub' => $x->event_date->toDateString() . ' · ' . $x->venue, 'url' => route('admin.events.index')];
        }
        return view('admin.ai.search', ['q' => $q, 'module' => $mod, 'modules' => $allowed, 'results' => $res, 'understood' => $f, 'aiOn' => $ai->enabled()]);
    }
}
