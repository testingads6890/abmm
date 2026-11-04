<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Family, Member};
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Mobile App" menu: who registered through the Flutter app, listed state-wise.
 * Scoping is automatic: Family / Member carry the HasArea scope, so Super Admin sees every state while a State Sub-Admin
 * (or District Operator) only ever sees rows of his own Pradesh / district.
 */
class AppUserController extends Controller
{
    private function families(Request $r)
    {
        return Family::query()->where('source', 'app')
            ->when($r->state, fn ($q, $v) => $v === '__none' ? $q->where(fn ($w) => $w->whereNull('state')->orWhere('state', '')) : $q->where('state', $v))
            ->when($r->district, fn ($q, $v) => $q->where('district', $v))
            ->when($r->from, fn ($q, $v) => $q->where('app_registered_at', '>=', $v . ' 00:00:00'))
            ->when($r->to, fn ($q, $v) => $q->where('app_registered_at', '<=', $v . ' 23:59:59'))
            ->when($r->q, fn ($q, $t) => $q->search($t));
    }

    /** state => [families, members] (always scoped to what the logged-in admin may see). */
    private function summary(): array
    {
        return Family::query()->where('source', 'app')
            ->selectRaw("COALESCE(NULLIF(state, ''), '__none') as st, COALESCE(NULLIF(pradesh, ''), '') as pr, COUNT(*) as fam, SUM(member_count) as mem, MAX(app_registered_at) as last_at")
            ->groupBy('st', 'pr')->orderByDesc('fam')->get()->all();
    }

    public function index(Request $r)
    {
        if (in_array($r->export, ['csv', 'xlsx', 'pdf'], true)) {
            $rows = (function () use ($r) { foreach ($this->families($r)->orderBy('id')->lazyById(1000) as $f) yield [$f->family_code, $f->surname . ' ' . $f->head_name, $f->mobile, $f->member_count, $f->tehsil ?: $f->city, $f->district, $f->state, $f->pradesh, $f->app_registered_at?->format('d-M-Y')]; })();
            return Exporter::download($r->export, 'app-registered-families', 'App Registered Families', ['Family ID', 'Head', 'Mobile', 'Members', 'Town', 'District', 'State', 'ABMM Pradesh', 'Registered'], $rows);
        }
        $districts = Family::query()->where('source', 'app')->when($r->state && $r->state !== '__none', fn ($q) => $q->where('state', $r->state))->whereNotNull('district')->distinct()->orderBy('district')->pluck('district');
        return view('admin.appusers.families', ['rows' => $this->families($r)->orderByDesc('id')->paginate(in_array((int) $r->per, [25, 50, 100]) ? (int) $r->per : 25)->withQueryString(), 'summary' => $this->summary(), 'districts' => $districts]);
    }

    public function members(Request $r)
    {
        $q = Member::query()->where('source', 'app')->with('family:id,family_code,state,district,pradesh')
            ->when($r->approval, fn ($q, $v) => $q->where('approval', $v))
            ->when($r->state, fn ($q, $v) => $q->whereIn('family_id', Family::query()->where('state', $v)->select('id')))
            ->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('surname', 'like', "%$t%")->orWhere('mobile', 'like', "%$t%")));
        return view('admin.appusers.members', ['rows' => $q->orderByDesc('id')->paginate(25)->withQueryString(), 'summary' => $this->summary()]);
    }
}
