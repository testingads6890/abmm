<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobProfile;
use App\Models\User;
use Illuminate\Http\Request;

/** Job seekers' profiles. Super Admin sees all; State Sub-Admin only their pradesh, District Operator their district (profiles carry the area of the family). */
class JobCandidateController extends Controller
{
    private function scoped()
    {
        $q = JobProfile::query();
        $u = auth()->user();
        if ($u->isSuper()) return $q;
        $f = $u->areaFilter();
        if (! $f || ! $u->hasArea()) return $q->whereRaw('1 = 0');
        foreach (array_intersect_key($f, array_flip(['pradesh', 'district', 'country', 'city', 'zone'])) as $col => $val) $q->where($col, $val);
        return $q;
    }

    public function index(Request $r)
    {
        $q = $this->scoped()
            ->when($r->query('open') === '1', fn ($x) => $x->where('open_to_work', true))
            ->when($r->query('category'), fn ($x, $v) => $x->where('category', $v))
            ->when($r->query('education'), fn ($x, $v) => $x->where('education', 'like', '%' . $v . '%'))
            ->when(in_array($r->query('gender'), ['Male', 'Female'], true), fn ($x) => $x->where('gender', $r->query('gender')))
            ->when($r->query('work_mode'), fn ($x, $v) => $x->where('work_mode', $v))
            ->when($r->query('notice_period'), fn ($x, $v) => $x->where('notice_period', $v))
            ->when($r->filled('exp_min'), fn ($x) => $x->where('experience_years', '>=', (int) $r->query('exp_min')))
            ->when($r->filled('exp_max'), fn ($x) => $x->where('experience_years', '<=', (int) $r->query('exp_max')))
            ->when($r->query('district'), fn ($x, $v) => $x->where('district', $v))
            ->when($r->query('q'), fn ($x, $s) => $x->where(fn ($w) => $w->where('name', 'like', '%' . $s . '%')->orWhere('headline', 'like', '%' . $s . '%')->orWhere('skills', 'like', '%' . $s . '%')->orWhere('city', 'like', '%' . $s . '%')));
        return view('admin.jobs.candidates', ['rows' => $q->orderByDesc('updated_at')->paginate(25)->withQueryString(), 'lk' => \App\Models\Lookup::grouped(), 'sd' => \App\Models\Lookup::stateDistricts()]);
    }

    public function destroy(int $id)
    {
        $this->scoped()->findOrFail($id)->delete();
        return back()->with('ok', 'Profile hata diya.');
    }
}
