<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Models\Lookup;
use App\Support\SalaryBand;
use App\Models\User;
use App\Services\GreetingService;
use Illuminate\Http\Request;

/** Jobs board moderation. Area-scoped by HasArea: State Sub-Admin sees own pradesh, District Operator own district. */
class JobController extends Controller
{
    public function index(Request $r)
    {
        $status = array_key_exists($r->query('status'), JobPost::STATUSES) ? $r->query('status') : 'pending';
        $band = collect(SalaryBand::bands('job_salary_range'))->firstWhere('label', $r->query('band'));
        $q = JobPost::withCount('applications')->where('status', $status)
            ->when(array_key_exists($r->query('type'), JobPost::TYPES), fn ($x) => $x->where('job_type', $r->query('type')))
            ->when($r->query('category'), fn ($x, $v) => $x->where('category', $v))
            ->when(in_array($r->query('work_mode'), ['onsite', 'remote', 'hybrid'], true), fn ($x) => $x->where('work_mode', $r->query('work_mode')))
            ->when($r->query('state'), fn ($x, $v) => $x->where('state', $v))
            ->when($r->query('district'), fn ($x, $v) => $x->where('district', $v))
            ->when($r->query('education'), fn ($x, $v) => $x->where('education', $v))
            ->when(in_array($r->query('gender_pref'), ['male', 'female'], true), fn ($x) => $x->where('gender_pref', $r->query('gender_pref')))
            ->when($band, fn ($x) => $x->whereNotNull('salary_min')->where('salary_min', '<=', $band['max'] ?? PHP_INT_MAX)->where(fn ($w) => $w->whereNull('salary_max')->orWhere('salary_max', '>=', $band['min'])))
            ->when($r->query('exp') !== null && $r->query('exp') !== '', fn ($x) => $x->where('exp_min', '<=', (int) $r->query('exp')))
            ->when($r->query('q'), fn ($x, $s) => $x->where(fn ($w) => $w->where('title', 'like', '%' . $s . '%')->orWhere('company', 'like', '%' . $s . '%')));
        return view('admin.jobs.index', [
            'rows' => $q->orderByDesc('id')->paginate(25)->withQueryString(), 'status' => $status, 'lk' => Lookup::grouped(), 'sd' => Lookup::stateDistricts(), 'bands' => SalaryBand::bands('job_salary_range'),
            'counts' => JobPost::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(int $id)
    {
        return view('admin.jobs.show', ['j' => JobPost::with('applications.profile')->findOrFail($id)]);
    }

    public function decide(Request $r, int $id)
    {
        $d = $r->validate(['status' => 'required|in:live,rejected,closed', 'days' => 'nullable|integer|min:1|max:365', 'note' => 'nullable|required_if:status,rejected|string|max:300']);
        $j = JobPost::findOrFail($id);
        abort_if($j->status === 'unpaid', 422, 'Payment for this job is pending.');
        $j->update([
            'status' => $d['status'], 'admin_note' => $d['note'] ?? null, 'decided_by' => auth()->id(), 'decided_at' => now(),
            'expires_at' => $d['status'] === 'live' ? now()->addDays((int) ($d['days'] ?? 30)) : $j->expires_at,
        ]);
        if ($d['status'] === 'live') { try { app(\App\Services\CareerService::class)->dispatchInstant($j->fresh()); } catch (\Throwable $e) { report($e); } }
        if ($d['status'] !== 'closed' && ($u = User::find($j->user_id))) {
            $live = $d['status'] === 'live';
            app(GreetingService::class)->notify($u, $live ? 'Your job post is live' : 'Your job post was not approved',
                $j->title . (filled($d['note'] ?? null) ? ' — ' . $d['note'] : ''), ['type' => 'job', 'id' => $j->id]);
        }
        return back()->with('ok', 'Updated.');
    }

    public function destroy(int $id)
    {
        JobPost::findOrFail($id)->delete();
        return redirect()->route('admin.jobs.index')->with('ok', 'Hata diya.');
    }
}
