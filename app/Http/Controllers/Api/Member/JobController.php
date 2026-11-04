<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\JobApplication;
use App\Models\JobPost;
use App\Models\JobProfile;
use App\Models\Lookup;
use App\Models\Member;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GreetingService;
use App\Support\MemberApp;
use App\Support\SalaryBand;
use Illuminate\Http\Request;

/**
 * Member app job portal (national - jobs and candidates of every state are visible to everybody).
 *  Employee / job seeker: profile + job preferences, search / filter jobs, "Jobs for me", one-tap apply, track applications.
 *  Employer / recruiter : post a job (admin approves), applicants, shortlist / select / reject, search candidates with filters.
 */
class JobController extends Controller
{
    public const WORK_MODES = ['onsite' => 'On-site', 'remote' => 'Work from home', 'hybrid' => 'Hybrid'];
    public const NOTICE = ['Immediate', '15 days', '30 days', '60 days', '90 days'];
    public const GENDER_PREF = ['any' => 'Male / Female dono', 'male' => 'Male only', 'female' => 'Female only'];

    /** Viewer's own pradesh (only used for the optional "my state" filter - the job portal itself is national). */
    private function pradesh(Request $r): ?string
    {
        return Family::withoutGlobalScopes()->whereKey($r->user()->family_id)->value('pradesh') ?: null;
    }

    private function like(string $s): string { return '%' . str_replace(['%', '_'], ['\%', '\_'], trim($s)) . '%'; }

    private function salaryLabel(?int $min, ?int $max): ?string
    {
        if ($min === null && $max === null) return null;
        if ($max === null) return SalaryBand::money($min, true) . '+ / month';
        if ($min === null || $min === $max) return SalaryBand::money($max, true) . ' / month';
        return SalaryBand::money($min, true) . ' - ' . SalaryBand::money($max, true) . ' / month';
    }

    private function expLabel(int $min, ?int $max): string
    {
        if ($min === 0 && $max === 0) return 'Fresher';
        if ($max === null) return $min === 0 ? 'Any experience' : "$min+ saal";
        return $min === $max ? "$min saal" : ($min === 0 ? "Fresher - $max saal" : "$min-$max saal");
    }

    public function item(JobPost $j, bool $full = false): array
    {
        return [
            'id' => $j->id, 'job_type' => $j->job_type, 'job_type_label' => JobPost::TYPES[$j->job_type] ?? $j->job_type, 'title' => $j->title, 'company' => $j->company,
            'city' => $j->city, 'salary' => $this->salaryLabel($j->salary_min, $j->salary_max) ?? $j->salary, 'salary_min' => $j->salary_min, 'salary_max' => $j->salary_max,
            'experience' => $this->expLabel((int) $j->exp_min, $j->exp_max), 'category' => $j->category, 'work_mode' => $j->work_mode, 'work_mode_label' => self::WORK_MODES[$j->work_mode] ?? $j->work_mode,
            'education' => $j->education, 'vacancies' => $j->vacancies, 'district' => $j->district, 'state' => $j->state,
            'status' => $j->status, 'status_label' => JobPost::STATUSES[$j->status] ?? $j->status, 'featured' => $j->isFeatured(), 'featured_until' => $j->featured_until?->toIso8601String(),
            'created_at' => $j->created_at?->toIso8601String(), 'expires_at' => $j->expires_at?->toIso8601String(),
        ] + ($full ? ['description' => $j->description, 'skills' => $j->skills, 'gender_pref' => $j->gender_pref, 'gender_pref_label' => self::GENDER_PREF[$j->gender_pref] ?? null,
            'contact_name' => $j->contact_name, 'contact_phone' => $j->contact_phone, 'admin_note' => $j->admin_note, 'views' => $j->views] : []);
    }

    private function profileItem(JobProfile $p, bool $withPhone): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'gender' => $p->gender, 'headline' => $p->headline, 'education' => $p->education, 'experience_years' => $p->experience_years, 'skills' => $p->skills,
            'city' => $p->city, 'district' => $p->district, 'job_type' => $p->job_type, 'job_type_label' => JobPost::TYPES[$p->job_type] ?? null, 'category' => $p->category,
            'work_mode' => $p->work_mode, 'preferred_cities' => $p->preferred_cities, 'expected_salary' => $p->expected_salary, 'expected_salary_num' => $p->expected_salary_num,
            'notice_period' => $p->notice_period, 'about' => $p->about, 'resume_url' => $p->resume_url, 'open_to_work' => $p->open_to_work,
        ] + ($withPhone ? ['phone' => $p->phone] : []);
    }

    public function meta()
    {
        $g = Lookup::grouped();
        $m = fn (array $a) => collect($a)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values();
        return [
            'types' => $m(JobPost::TYPES), 'work_modes' => $m(self::WORK_MODES), 'gender_pref' => $m(self::GENDER_PREF), 'notice_periods' => self::NOTICE,
            'categories' => $g['job_category'] ?? [], 'salary_bands' => SalaryBand::bands('job_salary_range'), 'education' => $g['education'] ?? [],
        ];
    }

    // ---------- Employee side ----------

    /**
     * GET jobs  : live jobs from all over India.
     * Filters: q, type, category, work_mode, salary_min / salary_max (Rs per month, overlap), education, exp (my years of experience), state, city, my_area=1, for_me=1 (use my profile preferences), sort=new|salary.
     */
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:80', 'salary_min' => 'nullable|integer|min:0', 'salary_max' => 'nullable|integer|min:0', 'exp' => 'nullable|integer|min:0|max:60',
            'page' => 'nullable|integer|min:1|max:200', 'sort' => 'nullable|in:new,salary']);
        $u = $r->user();
        $f = array_filter($r->only(['type', 'category', 'work_mode', 'education', 'state', 'city', 'exp', 'salary_min', 'salary_max']), fn ($v) => $v !== null && $v !== '');
        $mine = JobProfile::where('user_id', $u->id)->first();
        if ($r->boolean('for_me') && $mine) {          // "Jobs for me": fill what the seeker did not set from the profile preferences
            if ($mine->category) $f['category'] ??= $mine->category;
            if ($mine->work_mode) $f['work_mode'] ??= $mine->work_mode;
            $f['exp'] ??= $mine->experience_years;
        }
        $q = JobPost::withoutGlobalScopes()->live()
            ->when($r->boolean('my_area'), fn ($x) => $x->where('pradesh', $this->pradesh($r) ?? '-'))
            ->when(isset($f['type']) && array_key_exists($f['type'], JobPost::TYPES), fn ($x) => $x->where('job_type', $f['type']))
            ->when(isset($f['category']), fn ($x) => $x->where('category', $f['category']))
            ->when(isset($f['work_mode']), fn ($x) => $x->where('work_mode', $f['work_mode']))
            ->when(isset($f['education']), fn ($x) => $x->where(fn ($w) => $w->whereNull('education')->orWhere('education', $f['education'])))
            ->when(isset($f['state']), fn ($x) => $x->where('state', $f['state']))
            ->when(isset($f['city']), fn ($x) => $x->where('city', 'like', str_replace(['%', '_'], ['\%', '\_'], trim($f['city'])) . '%'))
            ->when(isset($f['exp']), fn ($x) => $x->where('exp_min', '<=', (int) $f['exp']))
            ->when(isset($f['salary_min']), fn ($x) => $x->whereNotNull('salary_min')->where(fn ($w) => $w->whereNull('salary_max')->orWhere('salary_max', '>=', (int) $f['salary_min'])))
            ->when(isset($f['salary_max']), fn ($x) => $x->whereNotNull('salary_min')->where('salary_min', '<=', (int) $f['salary_max']))
            ->when($mine?->gender && $r->boolean('for_me'), fn ($x) => $x->whereIn('gender_pref', ['any', strtolower($mine->gender)]))
            ->when(trim((string) $r->query('q')) !== '', fn ($x) => $x->where(fn ($w) => $w->where('title', 'like', $this->like($r->query('q')))
                ->orWhere('company', 'like', $this->like($r->query('q')))->orWhere('city', 'like', $this->like($r->query('q')))->orWhere('skills', 'like', $this->like($r->query('q')))));
        $q->orderByRaw('CASE WHEN featured_until IS NOT NULL AND featured_until > ? THEN 0 ELSE 1 END', [now()->toDateTimeString()]);   // featured jobs first
        $p = ($r->query('sort') === 'salary' ? $q->orderByDesc('salary_max') : $q)->orderByDesc('id')->simplePaginate(20);
        $applied = JobApplication::where('user_id', $u->id)->whereIn('job_id', $p->getCollection()->pluck('id'))->pluck('status', 'job_id');
        return [
            'data' => $p->getCollection()->map(fn ($j) => $this->item($j) + ['applied' => isset($applied[$j->id]), 'is_mine' => $j->user_id === $u->id])->values(),
            'next_page' => $p->hasMorePages() ? $p->currentPage() + 1 : null,
        ];
    }

    public function show(Request $r, int $id)
    {
        $u = $r->user();
        $j = JobPost::withoutGlobalScopes()->findOrFail($id);
        $own = $j->user_id === $u->id;
        abort_unless($own || ($j->status === 'live' && (! $j->expires_at || $j->expires_at->isFuture())), 404);
        if (! $own) $j->increment('views');
        $app = JobApplication::where('job_id', $j->id)->where('user_id', $u->id)->first();
        return ['data' => $this->item($j, true) + [
            'is_mine' => $own, 'applied' => (bool) $app, 'application_status' => $app?->status, 'has_profile' => JobProfile::where('user_id', $u->id)->exists(),
            'applicants_count' => $own ? $j->applications()->count() : null,
        ]];
    }

    /** POST jobs/{id}/apply  {note?} : needs a profile; one application per job. */
    public function apply(Request $r, int $id)
    {
        $d = $r->validate(['note' => 'nullable|string|max:500']);
        $u = $r->user();
        $j = JobPost::withoutGlobalScopes()->live()->findOrFail($id);
        abort_if($j->user_id === $u->id, 422, 'You cannot apply to your own job.');
        $prof = JobProfile::where('user_id', $u->id)->first();
        abort_unless($prof, 422, 'Create your job profile first.');
        abort_if($j->gender_pref !== 'any' && $prof->gender && strtolower($prof->gender) !== $j->gender_pref, 422, 'This job is only for ' . ($j->gender_pref === 'male' ? 'purush' : 'mahila') . ' candidates.');
        $app = JobApplication::firstOrCreate(['job_id' => $j->id, 'user_id' => $u->id], ['note' => $d['note'] ?? null]);
        if ($app->wasRecentlyCreated && ($owner = User::find($j->user_id))) {
            app(GreetingService::class)->notify($owner, 'New application: ' . $j->title, ($prof->name ?: 'Ek candidate') . ' has applied.', ['type' => 'job_applicant', 'id' => $j->id]);
        }
        return response()->json(['ok' => true, 'message' => $app->wasRecentlyCreated ? 'Application sent.' : 'You have already applied.']);
    }

    /** GET applications/mine : jobs I applied to, with status. */
    public function myApplications(Request $r)
    {
        $rows = JobApplication::with('job')->where('user_id', $r->user()->id)->orderByDesc('id')->limit(100)->get()->filter(fn ($a) => $a->job)->map(fn ($a) => [
            'id' => $a->id, 'status' => $a->status, 'status_label' => JobPost::APP_STATUSES[$a->status] ?? $a->status, 'applied_at' => $a->created_at?->toIso8601String(),
            'job' => $this->item($a->job),
        ])->values();
        return ['data' => $rows];
    }

    public function profile(Request $r)
    {
        $p = JobProfile::where('user_id', $r->user()->id)->first();
        $m = Member::withoutGlobalScopes()->whereKey($r->user()->member_id)->first(['name', 'surname', 'mobile', 'gender', 'education']);
        return ['data' => $p ? $this->profileItem($p, true) : null, 'defaults' => ['name' => trim(($m->name ?? '') . ' ' . ($m->surname ?? '')), 'phone' => $m->mobile ?? null, 'gender' => $m->gender ?? null, 'education' => $m->education ?? null]];
    }

    public function saveProfile(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:120', 'headline' => 'required|string|max:120', 'education' => 'nullable|string|max:150', 'experience_years' => 'nullable|integer|min:0|max:60',
            'skills' => 'nullable|string|max:300', 'city' => 'nullable|string|max:100', 'job_type' => 'nullable|in:' . implode(',', array_keys(JobPost::TYPES)),
            'expected_salary_num' => 'nullable|integer|min:0|max:10000000', 'about' => 'nullable|string|max:1500', 'phone' => ['required', 'regex:/^[0-9+\-\s]{8,18}$/'],
            'resume_url' => 'nullable|url|max:300', 'open_to_work' => 'nullable|boolean',
            'category' => ['nullable', 'string', 'max:80', MemberApp::inMaster('job_category')], 'work_mode' => 'nullable|in:' . implode(',', array_keys(self::WORK_MODES)),
            'preferred_cities' => 'nullable|string|max:200', 'notice_period' => 'nullable|in:' . implode(',', self::NOTICE),
        ]);
        $u = $r->user();
        $fam = $u->family_id ? Family::withoutGlobalScopes()->find($u->family_id) : null;
        $gender = Member::withoutGlobalScopes()->whereKey($u->member_id)->value('gender');
        if (isset($d['expected_salary_num'])) $d['expected_salary'] = SalaryBand::money((int) $d['expected_salary_num'], true) . ' / month';
        $p = JobProfile::updateOrCreate(['user_id' => $u->id], $d + ['experience_years' => $d['experience_years'] ?? 0, 'open_to_work' => $r->boolean('open_to_work', true),
            'member_id' => $u->member_id, 'pradesh' => $fam?->pradesh, 'district' => $fam?->district, 'gender' => $gender ? ucfirst(strtolower($gender)) : null]);
        return ['ok' => true, 'data' => $this->profileItem($p, true), 'message' => 'Profile saved.'];
    }

    // ---------- Employer side ----------

    /** GET jobs/mine : my posts with applicant counts. */
    public function mine(Request $r)
    {
        $rows = JobPost::withoutGlobalScopes()->withCount('applications')->where('user_id', $r->user()->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn ($j) => $this->item($j) + ['applicants_count' => $j->applications_count])->values();
        return ['data' => $rows];
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'job_type' => 'required|in:' . implode(',', array_keys(JobPost::TYPES)), 'title' => 'required|string|max:160', 'company' => 'nullable|string|max:160', 'city' => 'nullable|string|max:100',
            'category' => ['nullable', 'string', 'max:80', MemberApp::inMaster('job_category')], 'work_mode' => 'nullable|in:' . implode(',', array_keys(self::WORK_MODES)),
            'salary_min' => 'nullable|integer|min:0|max:10000000', 'salary_max' => 'nullable|integer|min:0|max:10000000|gte:salary_min', 'education' => 'nullable|string|max:120',
            'exp_min' => 'nullable|integer|min:0|max:60', 'exp_max' => 'nullable|integer|min:0|max:60|gte:exp_min', 'gender_pref' => 'nullable|in:' . implode(',', array_keys(self::GENDER_PREF)),
            'skills' => 'nullable|string|max:300', 'vacancies' => 'nullable|integer|min:1|max:500', 'description' => 'required|string|max:3000', 'contact_name' => 'nullable|string|max:100',
            'contact_phone' => ['required', 'regex:/^[0-9+\-\s]{8,18}$/'],
        ]);
        $u = $r->user();
        abort_if(JobPost::withoutGlobalScopes()->where('user_id', $u->id)->whereIn('status', ['unpaid', 'pending', 'live'])->count() >= 10, 422, 'You cannot keep more than 10 job posts at a time. Close an old post.');
        $fam = $u->family_id ? Family::withoutGlobalScopes()->find($u->family_id) : null;
        $j = JobPost::withoutGlobalScopes()->create($d + [
            'vacancies' => $d['vacancies'] ?? 1, 'work_mode' => $d['work_mode'] ?? 'onsite', 'gender_pref' => $d['gender_pref'] ?? 'any', 'exp_min' => $d['exp_min'] ?? 0,
            'salary' => $this->salaryLabel($d['salary_min'] ?? null, $d['salary_max'] ?? null), 'experience' => $this->expLabel((int) ($d['exp_min'] ?? 0), $d['exp_max'] ?? null),
            'user_id' => $u->id, 'family_id' => $fam?->id, 'member_id' => $u->member_id, 'pradesh' => $fam?->pradesh, 'state' => $fam?->state, 'district' => $fam?->district]);
        $career = app(\App\Services\CareerService::class);
        if ($career->paidPosting() && ($pack = $career->packs('post')->first())) {          // paid listing: goes for approval only after payment
            $j->update(['status' => 'unpaid']);
            [$oid, $pay] = $career->order($u, $j, $pack, $r->input('coupon'));
            return response()->json(['ok' => true, 'id' => $j->id, 'order_id' => $oid, 'payment_no' => $pay->number, 'checkout' => app(\App\Services\Payments\PaymentLedger::class)->checkout($pay, ['title' => $pack->name]),
                'message' => 'The job post will go for review after payment.'], 201);
        }
        UserNotification::notifyAdmins('New job post for approval: ' . $j->title, route('admin.jobs.show', $j->id), $fam?->pradesh);
        return response()->json(['ok' => true, 'id' => $j->id, 'message' => 'Your job post has been sent for review. It will be visible to everyone once approved.'], 201);
    }

    public function close(Request $r, int $id)
    {
        JobPost::withoutGlobalScopes()->where('user_id', $r->user()->id)->findOrFail($id)->update(['status' => 'closed']);
        return ['ok' => true];
    }

    /** GET jobs/{id}/applicants : only the employer who posted it. */
    public function applicants(Request $r, int $id)
    {
        $j = JobPost::withoutGlobalScopes()->where('user_id', $r->user()->id)->findOrFail($id);
        $rows = JobApplication::with('profile')->where('job_id', $j->id)->orderByDesc('id')->limit(200)->get()->filter(fn ($a) => $a->profile)->map(fn ($a) => [
            'id' => $a->id, 'status' => $a->status, 'status_label' => JobPost::APP_STATUSES[$a->status] ?? $a->status, 'note' => $a->note,
            'applied_at' => $a->created_at?->toIso8601String(), 'profile' => $this->profileItem($a->profile, true),
        ])->values();
        return ['job' => $this->item($j), 'data' => $rows];
    }

    /** POST applications/{id}/status {status: shortlisted|hired|rejected} : employer decides; applicant gets notified. */
    public function setApplicationStatus(Request $r, int $id)
    {
        $d = $r->validate(['status' => 'required|in:shortlisted,hired,rejected']);
        $a = JobApplication::with('job')->findOrFail($id);
        abort_unless($a->job && $a->job->user_id === $r->user()->id, 404);
        $a->update(['status' => $d['status']]);
        if ($u = User::find($a->user_id)) {
            app(GreetingService::class)->notify($u, 'Your application: ' . (JobPost::APP_STATUSES[$d['status']] ?? $d['status']), $a->job->title, ['type' => 'job_status', 'id' => $a->job_id]);
        }
        return ['ok' => true];
    }

    /**
     * GET candidates : job seekers who switched "open to work" on (all India).
     * Filters: q (headline / skills / education), category, education, exp_min / exp_max (years), budget (monthly Rs - candidates expecting up to this), city, gender, notice_period, work_mode, job_type.
     */
    public function candidates(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:80', 'exp_min' => 'nullable|integer|min:0|max:60', 'exp_max' => 'nullable|integer|min:0|max:60', 'budget' => 'nullable|integer|min:0',
            'gender' => 'nullable|in:male,female', 'page' => 'nullable|integer|min:1|max:200']);
        $q = JobProfile::where('open_to_work', true)->where('user_id', '!=', $r->user()->id)
            ->when(filled($r->query('city')), fn ($x) => $x->where(fn ($w) => $w->where('city', 'like', $this->like($r->query('city')))->orWhere('preferred_cities', 'like', $this->like($r->query('city')))))
            ->when(filled($r->query('category')), fn ($x) => $x->where('category', $r->query('category')))
            ->when(filled($r->query('education')), fn ($x) => $x->where('education', 'like', $this->like($r->query('education'))))
            ->when($r->filled('exp_min'), fn ($x) => $x->where('experience_years', '>=', (int) $r->query('exp_min')))
            ->when($r->filled('exp_max'), fn ($x) => $x->where('experience_years', '<=', (int) $r->query('exp_max')))
            ->when($r->filled('budget'), fn ($x) => $x->where(fn ($w) => $w->whereNull('expected_salary_num')->orWhere('expected_salary_num', '<=', (int) $r->query('budget'))))
            ->when(filled($r->query('gender')), fn ($x) => $x->where('gender', ucfirst($r->query('gender'))))
            ->when(filled($r->query('notice_period')), fn ($x) => $x->where('notice_period', $r->query('notice_period')))
            ->when(filled($r->query('work_mode')), fn ($x) => $x->where('work_mode', $r->query('work_mode')))
            ->when(array_key_exists((string) $r->query('job_type'), JobPost::TYPES), fn ($x) => $x->where('job_type', $r->query('job_type')))
            ->when(trim((string) $r->query('q')) !== '', fn ($x) => $x->where(fn ($w) => $w->where('headline', 'like', $this->like($r->query('q')))
                ->orWhere('skills', 'like', $this->like($r->query('q')))->orWhere('education', 'like', $this->like($r->query('q')))));
        $p = $q->orderByDesc('updated_at')->simplePaginate(20);
        return ['data' => $p->getCollection()->map(fn ($c) => $this->profileItem($c, true))->values(), 'next_page' => $p->hasMorePages() ? $p->currentPage() + 1 : null];
    }
}
