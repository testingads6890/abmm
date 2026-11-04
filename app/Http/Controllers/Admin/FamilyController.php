<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Family, Lookup, Member};
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class FamilyController extends Controller
{
    public function index(Request $r)
    {
        $in = fn (string $k) => array_values(array_filter((array) $r->input($k), fn ($v) => is_scalar($v) && trim((string) $v) !== ''));
        $families = Family::query();

        // 1) Search box: every word must match the family (head, surname, mobile, code, city) OR any member (name, surname, mobile, member ID, father/husband).
        foreach (preg_split('/\s+/', trim((string) $r->q), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            $like = '%' . addcslashes($w, '%_\\') . '%';
            $families->where(fn ($q) => $q->where('head_name', 'like', $like)->orWhere('surname', 'like', $like)->orWhere('mobile', 'like', $like)
                ->orWhere('family_code', 'like', $like)->orWhere('city', 'like', $like)
                ->orWhereHas('members', fn ($m) => $m->withoutGlobalScopes()->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('surname', 'like', $like)
                    ->orWhere('mobile', 'like', $like)->orWhere('member_code', 'like', $like)->orWhere('father_husband_name', 'like', $like))));
        }

        // 2) Family-level filters (the family record itself).
        foreach (['surname', 'tehsil', 'district', 'state', 'city', 'pradesh', 'caste', 'pincode'] as $k) {
            if ($vals = $in($k)) $families->whereIn($k, $vals);
        }
        // Typed surname: partial match, so "agar" finds Agarwal, Agrawal and so on.
        if (trim((string) $r->surname_text) !== '') {
            $families->where('surname', 'like', '%' . addcslashes(trim((string) $r->surname_text), '%_\\') . '%');
        }

        // 3) Person-level filters: a family matches when the family record OR any active member has the value.
        foreach (['gender', 'blood_group', 'business_type', 'education', 'occupation', 'profession', 'marital_status', 'gotra'] as $k) {
            if (! ($vals = $in($k))) continue;
            if ($k === 'gender') $vals = array_map(fn ($x) => ucfirst(strtolower($x)), $vals);
            $families->where(fn ($q) => $q->whereIn($k, $vals)->orWhereHas('members', fn ($m) => $m->withoutGlobalScopes()->where('status', 'active')->whereIn($k, $vals)));
        }

        // 4) Age range and birth month (any active member).
        $min = is_numeric($r->age_min) ? (int) $r->age_min : null;
        $max = is_numeric($r->age_max) ? (int) $r->age_max : null;
        $month = is_numeric($r->dob_month) && $r->dob_month >= 1 && $r->dob_month <= 12 ? (int) $r->dob_month : null;
        if ($min !== null || $max !== null || $month !== null) {
            $families->whereHas('members', function ($m) use ($min, $max, $month) {
                $m->withoutGlobalScopes()->where('status', 'active')->whereNotNull('dob');
                if ($min !== null) $m->whereDate('dob', '<=', now()->subYears($min)->toDateString());
                if ($max !== null) $m->whereDate('dob', '>', now()->subYears($max + 1)->toDateString());
                if ($month !== null) $m->whereMonth('dob', $month);
            });
        }

        // 5) Yes / No flags and registration dates.
        foreach (['has_mediclaim', 'pakshik_member'] as $k) {
            if (in_array($r->input($k), ['yes', 'no'], true)) $families->where($k, $r->input($k) === 'yes');
        }
        if ($r->filled('reg_from') && strtotime((string) $r->reg_from)) $families->whereDate('created_at', '>=', $r->reg_from);
        if ($r->filled('reg_to') && strtotime((string) $r->reg_to)) $families->whereDate('created_at', '<=', $r->reg_to);

        // 6) Advanced: Anchal (all its ABMM Pradesh), income band, resident type, surname letter.
        if ($r->filled('anchal')) {
            $list = Lookup::pradeshesOf($r->anchal);
            $list ? $families->whereIn('pradesh', $list) : $families->whereRaw('1 = 0');
        }
        if ($r->filled('income_range')) $families->where('income_range', $r->income_range);
        if ($r->filled('resident_type')) $families->where('resident_type', $r->resident_type);
        if ($r->filled('letter') && preg_match('/^[A-Z]$/i', (string) $r->letter)) $families->where('surname', 'like', strtoupper($r->letter) . '%');

        $av = $in('pradesh_avail');
        if ($av && count($av) === 1) {
            $av[0] === 'yes'
                ? $families->whereNotNull('pradesh')->where('pradesh', '!=', '')
                : $families->where(fn ($w) => $w->whereNull('pradesh')->orWhere('pradesh', ''));
        }
        $families->when($r->status, fn ($q, $v) => $q->where('status', $v), fn ($q) => $q->where('status', 'active'));
        if (in_array($r->input('export'), ['csv', 'xlsx'], true)) {
            abort_unless($r->user()->can('families.export'), 403);
            return $this->exportFiltered($r->input('export'), $families);
        }
        $families = $families->latest()->paginate(in_array((int) $r->per, [10, 25, 50, 100, 250]) ? (int) $r->per : 25)->withQueryString();

        // Option lists: masters plus the values actually present in the data, so every filter can find every record.
        $lk = $this->lookups();
        // Super Admin: masters + values in the data. Other users: only the places inside their own area (the family query is already area-scoped).
        $scoped = ! auth()->user()?->isSuper();
        foreach (['district', 'state', 'city', 'pradesh', 'tehsil', 'caste', 'gotra', 'surname'] as $c) {
            $fromData = Family::query()->whereNotNull($c)->where($c, '!=', '')->distinct()->pluck($c);
            $lk[$c] = collect($scoped && in_array($c, ['district', 'state', 'city', 'pradesh', 'tehsil'], true) ? [] : ($lk[$c] ?? []))
                ->merge($fromData)->unique()->sort()->values()->all();
        }
        $lk['marital_status'] = collect($lk['marital_status'] ?? [])->merge(Member::withoutGlobalScopes()->whereNotNull('marital_status')->distinct()->pluck('marital_status'))->unique()->sort()->values()->all();
        return view('admin.families.index', ['families' => $families, 'lk' => $lk]);
    }

    /** Villages / towns / cities of one tehsil (LGD data, database/data/villages), for the City suggestions. Only inside the user's area. */
    public function villages(Request $r)
    {
        $d = $r->validate(['state' => 'required|string|max:120', 'district' => 'required|string|max:120', 'tehsil' => 'required|string|max:120']);
        $tehsil = strtoupper(trim($d['tehsil']));
        $district = strtoupper(trim($d['district']));
        $state = strtoupper(trim($d['state']));
        $allowed = collect(Lookup::tehsilMetaFor(auth()->user()))->first(fn ($m, $name) => $name === $tehsil && ($m['district'] ?? '') === $district && ($m['state'] ?? '') === $state);
        if (! $allowed) return ['villages' => []];
        $slug = fn (string $x) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($x)), '-');
        $file = database_path('data/villages/' . $slug($state) . '--' . $slug($district) . '.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $list = $data['tehsils'][$tehsil] ?? [];
        return ['villages' => array_slice($list, 0, 3000)];
    }

    /** Same filters as the search page, downloaded as the Members CSV/Excel layout (every active member of each matching family). */
    private function exportFiltered(string $format, $families)
    {
        $with = fn ($m) => $m->withoutGlobalScopes()->where('status', 'active')->orderByDesc('is_head')->orderBy('id');
        $gen = (function () use ($families, $with) {
            foreach ($families->with(['members' => $with])->orderBy('id')->lazy(500) as $f) {
                foreach ($f->members as $m) yield MemberImportController::row($f, $m);
            }
        })();
        ActivityLog::create(['user_id' => auth()->id(), 'action' => 'MEMBER_SEARCH_EXPORT', 'detail' => $format, 'ip' => request()->ip()]);
        return Exporter::download($format, 'abmm-member-search', 'Members (search results)', MemberImportController::HEADERS, $gen);
    }

    public function create() { return view('admin.families.form', $this->formData(new Family(['country' => 'INDIA', 'member_count' => 1, 'resident_type' => 'OWNED']))); }

    public function store(Request $r)
    {
        $d = $this->validated($r);
        $d['family_code'] = Family::nextCode();
        $d['created_by'] = $r->user()->id;
        if ($r->hasFile('photo')) $d['photo'] = $r->file('photo')->store('families', 'public');
        $family = Family::create($d);
        $family->syncHead();
        return redirect()->route('admin.families.show', $family)->with('ok', "Family registered. Family ID: {$family->family_code}");
    }

    public function show(Family $family) { return view('admin.families.show', ['family' => $family->load('members'), 'lk' => $this->lookups(), 'casteGotras' => Lookup::casteGotras(), 'countryCodes' => Lookup::countryCodes()]); }

    public function edit(Family $family) { return view('admin.families.form', $this->formData($family)); }

    public function update(Request $r, Family $family)
    {
        $d = $this->validated($r, $family);
        if ($r->hasFile('photo')) {
            if ($family->photo) Storage::disk('public')->delete($family->photo);
            $d['photo'] = $r->file('photo')->store('families', 'public');
        }
        if (! $r->filled('aadhar')) unset($d['aadhar']);
        $family->update($d);
        $family->syncHead();
        if ($r->boolean('update_surname_all')) $family->members()->update(['surname' => $family->surname]);
        return redirect()->route('admin.families.show', $family)->with('ok', 'Family updated.');
    }

    public function destroy(Family $family)
    {
        $family->update(['status' => 'inactive']); // soft retire, never hard-delete member data
        return redirect()->route('admin.families.index')->with('ok', 'Family marked inactive.');
    }

    private function memberRules(): array
    {
        return ['name' => 'required|max:100', 'father_husband_name' => 'nullable|max:120', 'relation' => ['required', 'max:40', Rule::in($this->opts('relation', explode('|', 'AUNTY|BROTHER|BROTHER-IN-LAW|CHACHA (KAKAJI)|CHACHI (KAKIJI)|DAUGHTER|DAUGHTER OF GRANDSON|DAUGHTER-IN-LAW (BAHU)|FATHER|FATHER-IN-LAW (SASUR)|GRAND DAUGHTER (POTI)|GRAND DAUGHTER-IN-LAW|GRAND MOTHER|GRAND SON (POTA)|GRANDFATHER|HUSBAND|MOTHER|MOTHER-IN-LAW|NEPHEW (BHATIJA)|NEPHEWS WIFE|NIECE (BHATIJI)|OTHER|SISTER|SISTER-IN-LAW|SON|SON OF GRANDSON|SPOUSE|STEP MOTHER|UNCLE|WIFE')))],
            'gender' => ['required', Rule::in($this->opts('gender', ['MALE', 'FEMALE']))], 'dob' => 'nullable|date|before:today', 'anniversary' => 'nullable|date',
            'blood_group' => 'nullable|max:5', 'education' => 'nullable|max:100', 'education_detail' => 'nullable|max:200', 'occupation' => 'nullable|max:100', 'occupation_detail' => 'nullable|max:200',
            'profession' => 'nullable|max:100', 'business_type' => 'nullable|max:100', 'marital_status' => 'nullable|max:20', 'mobile' => ['nullable', \App\Support\MemberApp::mobileRule()], 'country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'], 'email' => 'nullable|email',
            'caste' => ['nullable', 'max:60', \App\Support\MemberApp::inMaster('caste')], 'gotra' => ['nullable', 'max:80', \App\Support\MemberApp::inMaster('gotra')],
            'aadhar' => 'nullable|digits:12', 'photo' => 'nullable|image|max:2048'];
    }

    private function memberData(Request $r, ?Member $member = null): array
    {
        $d = $r->validate($this->memberRules());
        $d['extra'] = \App\Support\CustomForm::collect($r, 'member', $member?->extra);
        $d['gender'] = ucfirst(strtolower($d['gender']));
        if ($r->hasFile('photo')) $d['photo'] = $r->file('photo')->store('members', 'public'); else unset($d['photo']);
        if (empty($d['aadhar'])) unset($d['aadhar']);
        return $d;
    }

    public function addMember(Request $r, Family $family)
    {
        $family->members()->create($this->memberData($r) + ['surname' => $family->surname]);
        $family->refreshCount();
        return back()->with('ok', 'Member added.');
    }

    public function updateMember(Request $r, Family $family, Member $member)
    {
        abort_unless($member->family_id === $family->id, 404);
        $member->update($this->memberData($r, $member));
        return back()->with('ok', 'Member updated.');
    }

    /** Moves a member out of the family into "Hanging Data" (Died / Married / Migrated / Naya Chulha / Wrong Entry). */
    public function archiveMember(Request $r, Family $family, Member $member)
    {
        abort_unless($member->family_id === $family->id && ! $member->is_head, 404);
        $d = $r->validate(['archive_type' => 'required|in:' . implode(',', Member::ARCHIVE_TYPES), 'archive_note' => 'nullable|max:200']);
        $member->update($d + ['status' => 'archived', 'archived_at' => now()]);
        $family->refreshCount();
        return back()->with('ok', 'Member moved to Hanging Data.');
    }

    public function restoreMember(Family $family, Member $member)
    {
        abort_unless($member->family_id === $family->id, 404);
        $member->update(['status' => 'active', 'archive_type' => null, 'archived_at' => null, 'archive_note' => null]);
        $family->refreshCount();
        return back()->with('ok', 'Member added back to the family.');
    }

    /** Super Admin and State Sub-Admin: move a member (not the head) into another family as an active member. */
    public function migrateMember(Request $r, Family $family, Member $member)
    {
        abort_unless($r->user()->isSuper() || $r->user()->isStateAdmin(), 403);
        abort_unless($member->family_id === $family->id && ! $member->is_head, 404);
        $d = $r->validate(['target_family_code' => 'required|string|max:20']);
        // Family is area-scoped, so a sub-admin can only migrate into a family in their own area.
        $target = Family::where('family_code', trim($d['target_family_code']))->where('id', '!=', $family->id)->first();
        if (! $target) return back()->withErrors(['target_family_code' => 'Family ID not found in your area.']);
        $member->update(['family_id' => $target->id, 'status' => 'active', 'archive_type' => null, 'archived_at' => null, 'archive_note' => null]);
        $family->refreshCount();
        $target->refreshCount();
        return back()->with('ok', "Member migrated to {$target->family_code}.");
    }

    public function removeMember(Family $family, Member $member)
    {
        abort_unless($member->family_id === $family->id && ! $member->is_head, 404);
        $member->update(['status' => 'archived', 'archive_type' => 'Wrong Entry', 'archived_at' => now()]); // never hard-delete
        $family->refreshCount();
        return back()->with('ok', 'Member archived as Wrong Entry.');
    }

    public function toggle(Family $family)
    {
        $family->update(['status' => $family->status === 'inactive' ? 'active' : 'inactive']);
        return back()->with('ok', $family->status === 'active' ? 'Family activated.' : 'Family deactivated (it will not appear in downloads).');
    }

    public function toggleMember(Member $member)
    {
        abort_if($member->status === 'archived', 404);
        $member->update(['status' => $member->status === 'inactive' ? 'active' : 'inactive']);
        return back()->with('ok', $member->status === 'active' ? 'Member activated.' : 'Member deactivated.');
    }

    public function card(Family $family)
    {
        return view('admin.families.card', ['family' => $family->load('activeMembers')]);
    }

    /** Active master values for a dropdown (case-insensitive match is done by normalising gender below). */
    private function opts(string $type, array $fallback): array
    {
        $v = Lookup::grouped()[$type] ?? $fallback;
        return $type === 'gender' ? array_values(array_unique(array_merge($v, array_map(fn ($x) => ucfirst(strtolower($x)), $v), ['Other']))) : $v;
    }

    private function validated(Request $r, ?Family $family = null): array
    {
        $d = $r->validate([
            'surname' => 'required|max:80', 'caste' => ['nullable', 'max:60', \App\Support\MemberApp::inMaster('caste')], 'gotra' => ['nullable', 'max:80', \App\Support\MemberApp::inMaster('gotra')], 'head_name' => 'required|max:120', 'father_husband_name' => 'required|max:120', 'mother_name' => 'nullable|max:120',
            'mobile' => 'required|digits:10', 'whatsapp' => 'nullable|digits:10', 'gender' => ['required', Rule::in($this->opts('gender', ['MALE', 'FEMALE']))], 'member_count' => 'required|integer|min:1|max:60',
            'resident_type' => ['required', Rule::in($this->opts('resident_type', ['OWNED', 'RENTED', 'GOVT. QUARTER']))], 'address' => 'required|max:500', 'city' => 'nullable|max:100', 'country' => 'required|max:60',
            'state' => 'nullable|max:80', 'district' => 'required|max:80', 'tehsil' => 'required|max:80', 'pradesh' => 'nullable|max:120', 'pincode' => 'required|digits:6',
            'aadhar' => 'nullable|digits:12', 'phone' => 'nullable|max:20', 'email' => 'nullable|email', 'dob' => 'required|date|before:today',
            'marital_status' => 'required|max:20', 'anniversary' => 'nullable|date', 'blood_group' => 'nullable|max:5',
            'education' => 'nullable|max:100', 'education_detail' => 'nullable|max:200', 'occupation' => 'nullable|max:100', 'occupation_detail' => 'nullable|max:200',
            'business_type' => 'nullable|max:100', 'profession' => 'nullable|max:100', 'income_range' => 'required|max:60',
            'has_mediclaim' => 'boolean', 'pakshik_member' => 'boolean', 'status' => 'nullable|in:active,inactive,moved', 'photo' => 'nullable|image|max:2048',
        ]);
        $d['gender'] = ucfirst(strtolower($d['gender']));
        $d['extra'] = \App\Support\CustomForm::collect($r, 'family', $family?->extra);
        $d['caste'] = $d['caste'] ?: ($family?->caste ?: 'Maheshwari'); // caste is hidden on the form; default Maheshwari
        if (strcasecmp((string) $d['marital_status'], 'married') !== 0) $d['anniversary'] = null; // anniversary only for married
        $d['anchal'] = Lookup::anchalOf($d['pradesh'] ?? null); // stored, derived from the chosen ABMM Pradesh
        return $d;
    }

    private function lookups(): array
    {
        return Lookup::grouped();
    }

    private function formData(Family $family): array
    {
        // Village / Town / City values already used under each Tehsil / Zone (suggestions only; any value can still be typed).
        // Uses the normal area scope, so a sub-admin only gets suggestions from their own area.
        $cityByTehsil = Family::query()->whereNotNull('tehsil')->where('tehsil', '!=', '')->whereNotNull('city')->where('city', '!=', '')
            ->select('tehsil', 'city')->distinct()->get()->groupBy('tehsil')
            ->map(fn ($g) => $g->pluck('city')->unique()->sort()->values()->all())->all();
        // Older families may have no date of birth: show a default date so the record can be saved (new families must enter one).
        if ($family->exists && ! $family->dob) $family->dob = '1900-01-01';
        return ['family' => $family, 'lk' => $this->lookups(), 'tehsilMeta' => Lookup::tehsilMetaFor(auth()->user()), 'casteGotras' => Lookup::casteGotras(), 'cityByTehsil' => $cityByTehsil];
    }
}
