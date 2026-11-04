<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use App\Models\MatrimonyProfile;
use App\Models\Member;
use App\Models\User;
use App\Services\GreetingService;
use App\Support\MemberApp;
use App\Support\SalaryBand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mangal Parinay inside the app (national - every state sees every state).
 * Only PAID profiles can search, send interests and are listed. Phone + address open only after an interest is accepted.
 * A family can have several profiles (son, daughter...): pass profile_id to choose who is "acting".
 */
class MatrimonyController extends Controller
{
    private const FILTER_ARRAYS = ['qualification', 'degree', 'occupation_type', 'marital', 'state', 'diet'];

    // ---------- helpers ----------

    private function familyProfiles(Request $r)
    {
        $ids = Member::withoutGlobalScopes()->where('family_id', $r->user()->family_id)->pluck('id');
        return MatrimonyProfile::withoutGlobalScopes()->whereIn('member_id', $ids);
    }

    public static function isPaidPublic(MatrimonyProfile $p): bool { return self::isPaid($p); }

    private static function isPaid(MatrimonyProfile $p): bool
    {
        return $p->status === 'paid' && (! $p->paid_until || $p->paid_until->gte(today()));   // admin-marked Paid profiles may have no end date
    }

    /** The profile the user acts as. $needPaid: browsing / interests need an active paid profile. */
    public function acting(Request $r, bool $needPaid = true): MatrimonyProfile
    {
        $q = $this->familyProfiles($r);
        $pid = $r->input('profile_id', $r->query('profile_id'));
        $p = $pid ? $q->findOrFail($pid) : $q->orderByRaw("status = 'paid' desc")->orderByDesc('paid_until')->first();
        abort_unless($p, 404, 'Register in Mangal Parinay first.');
        abort_if($needPaid && ! self::isPaid($p), 403, 'The Mangal Parinay fee (Paid) is required to view profiles and send interests.');
        return $p;
    }

    private function ownerUser(MatrimonyProfile $p): ?User
    {
        return User::where('role', 'member')->where('is_active', true)->where('member_id', $p->member_id)->first()
            ?? User::where('role', 'member')->where('is_active', true)->where('family_id', $p->family_id)->orderBy('id')->first();
    }

    private function bandLabel(?int $v): ?string
    {
        if ($v === null) return null;
        foreach (SalaryBand::bands('salary_range') as $b) if ($v >= $b['min'] && ($b['max'] === null || $v <= $b['max'])) return $b['label'];
        return SalaryBand::money($v);
    }

    /** Public card (no phone / address). */
    public function card(MatrimonyProfile $p, array $short = [], array $interest = []): array
    {
        return [
            'id' => $p->id, 'parinay_id' => $p->parinay_id, 'name' => $p->name, 'gender' => $p->gender, 'age' => $p->dob?->age, 'height_cm' => $p->height_cm, 'height' => $p->height,
            'marital_status' => $p->marital_status, 'qualification' => $p->qualification, 'degree' => $p->degree, 'occupation_type' => $p->occupation_type, 'occupation' => $p->occupation,
            'income' => $this->bandLabel($p->annual_income), 'city' => $p->city, 'district' => $p->district, 'state' => $p->state, 'gotra' => $p->gotra, 'manglik' => (bool) $p->manglik, 'diet' => $p->diet,
            'about' => $p->about ? mb_strimwidth($p->about, 0, 140, '…') : null, 'photo_url' => MemberApp::photoUrl($p->photo),
            'shortlisted' => in_array($p->id, $short, true), 'interest' => $interest[$p->id] ?? null,   // sent | received | accepted | declined
        ];
    }

    /** shortlist ids + interest state (per other profile) for the acting profile. */
    public function relations(MatrimonyProfile $me, $ids): array
    {
        $short = DB::table('matrimony_shortlists')->where('profile_id', $me->id)->whereIn('target_id', $ids)->pluck('target_id')->all();
        $interest = [];
        foreach (DB::table('matrimony_interests')->where(fn ($q) => $q->where('from_profile_id', $me->id)->whereIn('to_profile_id', $ids))
            ->orWhere(fn ($q) => $q->where('to_profile_id', $me->id)->whereIn('from_profile_id', $ids))->get() as $i) {
            $mine = $i->from_profile_id === $me->id;
            $other = $mine ? $i->to_profile_id : $i->from_profile_id;
            $interest[$other] = in_array($i->status, ['accepted', 'declined'], true) ? $i->status : ($mine ? 'sent' : 'received');
        }
        return [$short, $interest];
    }

    // ---------- meta + my profile ----------

    public function meta()
    {
        $g = Lookup::grouped();
        return [
            'qualification' => $g['parinay_qualification'] ?? [], 'degree' => $g['parinay_degree'] ?? [], 'occupation_type' => $g['parinay_occupation_type'] ?? [], 'occupation' => $g['parinay_occupation'] ?? [],
            'marital' => $g['parinay_marital'] ?? [], 'diet' => $g['diet'] ?? [], 'income_bands' => SalaryBand::bands('salary_range'), 'gotra' => $g['gotra'] ?? [],
            'states' => array_keys(Lookup::ofType('state')->active()->orderBy('name')->get(['name'])->keyBy('name')->all()), 'height_min' => 120, 'height_max' => 220, 'age_min' => 18, 'age_max' => 70,
        ];
    }

    /** GET matrimony/profiles : my family's profiles with status (so the app can choose who is acting). */
    public function profiles(Request $r)
    {
        return ['data' => $this->familyProfiles($r)->get()->map(fn ($p) => ['id' => $p->id, 'parinay_id' => $p->parinay_id, 'name' => $p->name, 'gender' => $p->gender, 'paid' => self::isPaid($p), 'paid_until' => $p->paid_until?->toDateString()])->values()];
    }

    /** GET matrimony/me?profile_id= : my full profile + partner preferences. */
    public function me(Request $r)
    {
        $p = $this->acting($r, false);
        return ['data' => $this->card($p) + [
            'qualification' => $p->qualification, 'height_cm' => $p->height_cm, 'annual_income_band' => $this->bandLabel($p->annual_income), 'country' => $p->country, 'about_full' => $p->about,
            'visible' => (bool) $p->visible, 'partner_pref' => $p->partner_pref ?: (object) [], 'paid' => self::isPaid($p), 'paid_until' => $p->paid_until?->toDateString(),
            'interests_received' => DB::table('matrimony_interests')->where('to_profile_id', $p->id)->where('status', 'sent')->count(),
        ]];
    }

    /** POST matrimony/me : save profile details + partner preferences. */
    public function saveMe(Request $r)
    {
        $d = $r->validate([
            'profile_id' => 'nullable|integer', 'marital_status' => 'nullable|string|max:20', 'gotra' => ['nullable', 'string', 'max:80', MemberApp::inMaster('gotra')], 'height_cm' => 'nullable|integer|min:100|max:230',
            'income_band' => 'nullable|string|max:60', 'diet' => 'nullable|string|max:20', 'city' => 'nullable|string|max:100', 'country' => 'nullable|string|max:60', 'qualification' => 'nullable|string|max:120',
            'degree' => 'nullable|string|max:120', 'occupation_type' => 'nullable|string|max:60', 'occupation' => 'nullable|string|max:120', 'manglik' => 'nullable|boolean', 'about' => 'nullable|string|max:1000',
            'visible' => 'nullable|boolean',
            'pref' => 'nullable|array', 'pref.age_min' => 'nullable|integer|min:18|max:90', 'pref.age_max' => 'nullable|integer|min:18|max:90', 'pref.height_min' => 'nullable|integer|min:100|max:230',
            'pref.height_max' => 'nullable|integer|min:100|max:230', 'pref.income_min' => 'nullable|integer|min:0', 'pref.manglik' => 'nullable|in:any,yes,no', 'pref.note' => 'nullable|string|max:300',
            'pref.qualification' => 'nullable|array|max:20', 'pref.qualification.*' => 'string|max:120', 'pref.marital' => 'nullable|array|max:6', 'pref.marital.*' => 'string|max:20',
            'pref.state' => 'nullable|array|max:40', 'pref.state.*' => 'string|max:80', 'pref.diet' => 'nullable|array|max:6', 'pref.diet.*' => 'string|max:20',
        ]);
        $p = $this->acting($r, false);
        $upd = collect($d)->only(['marital_status', 'gotra', 'height_cm', 'diet', 'city', 'country', 'qualification', 'degree', 'occupation_type', 'occupation', 'about'])->all();
        if ($r->has('manglik')) $upd['manglik'] = $r->boolean('manglik');
        if ($r->has('visible')) $upd['visible'] = $r->boolean('visible');
        if (! empty($d['height_cm'])) $upd['height'] = intdiv((int) round($d['height_cm'] / 2.54), 12) . "'" . ((int) round($d['height_cm'] / 2.54) % 12) . '"';
        if (array_key_exists('income_band', $d)) {
            $hit = collect(SalaryBand::bands('salary_range'))->firstWhere('label', $d['income_band']);
            $upd['annual_income'] = $hit ? (int) ($hit['max'] === null ? $hit['min'] * 1.25 : ($hit['min'] === 0 ? $hit['max'] / 2 : ($hit['min'] + $hit['max']) / 2)) : null;
        }
        if (isset($d['pref'])) $upd['partner_pref'] = array_filter($d['pref'], fn ($v) => $v !== null && $v !== [] && $v !== '');
        if (! $p->photo && ($mp = Member::withoutGlobalScopes()->whereKey($p->member_id)->value('photo'))) $upd['photo'] = $mp;   // profile picture = member's photo
        $p->update($upd);
        return ['ok' => true, 'message' => 'Profile saved.'];
    }

    // ---------- search ----------

    /** Array filters arrive as ?qualification[]=A&qualification[]=B, a JSON string '["A","B"]' (the app) or a comma list. */
    private static function listParam(mixed $v): array
    {
        if (is_string($v)) {
            $t = trim($v);
            $v = $t === '' ? [] : (str_starts_with($t, '[') ? (json_decode($t, true) ?: []) : explode(',', $t));
        }
        return is_array($v) ? array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : '', $v), fn ($x) => $x !== '')) : [];
    }

    /**
     * GET matrimony/search  (profile_id, mode=all|matches, page, q + every filter)
     * Filters: age_min/max, height_min/max (cm), qualification[], degree[], occupation_type[], marital[], income_min/max (Rs / year),
     * manglik=any|yes|no, state[], city, diet[], gotra_exclude=1 (default: skip my own gotra), q = name or Parinay ID.
     */
    public function search(Request $r)
    {
        foreach (self::FILTER_ARRAYS as $k) $r->merge([$k => array_slice(self::listParam($r->input($k)), 0, 40)]);
        $r->validate([
            'age_min' => 'nullable|integer|min:18|max:90', 'age_max' => 'nullable|integer|min:18|max:90', 'height_min' => 'nullable|integer|min:100|max:230', 'height_max' => 'nullable|integer|min:100|max:230',
            'income_min' => 'nullable|integer|min:0', 'income_max' => 'nullable|integer|min:0', 'manglik' => 'nullable|in:any,yes,no', 'city' => 'nullable|string|max:100', 'q' => 'nullable|string|max:60',
            'mode' => 'nullable|in:all,matches', 'page' => 'nullable|integer|min:1|max:50',
        ] + collect(self::FILTER_ARRAYS)->flatMap(fn ($k) => [$k => 'nullable|array|max:40', "$k.*" => 'string|max:120'])->all());
        $me = $this->acting($r);
        $f = [];
        if ($r->input('mode') === 'matches') {                       // "Matches for me": my partner preferences
            $pref = (array) $me->partner_pref;
            foreach (['age_min', 'age_max', 'height_min', 'height_max', 'income_min', 'manglik', 'state', 'qualification', 'marital', 'diet'] as $k) if (isset($pref[$k])) $f[$k] = $pref[$k];
        }
        foreach (['age_min', 'age_max', 'height_min', 'height_max', 'income_min', 'income_max', 'manglik', 'city', 'q', ...self::FILTER_ARRAYS] as $k) {
            $v = $r->input($k);
            if ($v !== null && $v !== '' && $v !== []) $f[$k] = $v;
        }

        $q = MatrimonyProfile::withoutGlobalScopes()->where('status', 'paid')->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '>=', today()))->where('visible', true)
            ->where('id', '!=', $me->id)->where('gender', strcasecmp((string) $me->gender, 'male') === 0 ? 'Female' : 'Male');
        if (! empty($f['age_min'])) $q->where('dob', '<=', now()->subYears((int) $f['age_min'])->toDateString());
        if (! empty($f['age_max'])) $q->where('dob', '>=', now()->subYears((int) $f['age_max'] + 1)->addDay()->toDateString());
        if (! empty($f['height_min'])) $q->where('height_cm', '>=', (int) $f['height_min']);
        if (! empty($f['height_max'])) $q->where('height_cm', '<=', (int) $f['height_max']);
        if (isset($f['income_min'])) $q->where('annual_income', '>=', (int) $f['income_min']);
        if (isset($f['income_max'])) $q->where('annual_income', '<=', (int) $f['income_max']);
        foreach (['qualification' => 'qualification', 'degree' => 'degree', 'occupation_type' => 'occupation_type', 'marital' => 'marital_status', 'state' => 'state', 'diet' => 'diet'] as $key => $col) {
            if (! empty($f[$key])) $q->whereIn($col, (array) $f[$key]);
        }
        if (($f['manglik'] ?? 'any') === 'yes') $q->where('manglik', true);
        if (($f['manglik'] ?? 'any') === 'no') $q->where('manglik', false);
        if (! empty($f['city'])) $q->where('city', 'like', str_replace(['%', '_'], ['\%', '\_'], trim($f['city'])) . '%');
        if ((string) $r->input('gotra_exclude', '1') !== '0' && $me->gotra) $q->where(fn ($w) => $w->whereNull('gotra')->orWhere('gotra', '!=', $me->gotra));   // same-gotra marriage is avoided
        if (! empty($f['q'])) {
            $t = trim($f['q']);
            $q->where(fn ($w) => $w->where('parinay_id', $t)->orWhere('name', 'like', str_replace(['%', '_'], ['\%', '\_'], $t) . '%'));
        }

        $p = $q->orderByDesc('registered_at')->simplePaginate(20);
        [$short, $interest] = $this->relations($me, $p->getCollection()->pluck('id'));
        return ['data' => $p->getCollection()->map(fn ($x) => $this->card($x, $short, $interest))->values(), 'next_page' => $p->hasMorePages() ? $p->currentPage() + 1 : null];
    }

    /** GET matrimony/{id}?profile_id= : full profile; phone + address only after an accepted interest. */
    public function show(Request $r, int $id)
    {
        $me = $this->acting($r);
        $p = MatrimonyProfile::withoutGlobalScopes()->findOrFail($id);
        abort_unless($p->id === $me->id || (self::isPaid($p) && $p->visible), 404);
        [$short, $interest] = $this->relations($me, [$p->id]);
        $open = $p->id === $me->id || ($interest[$p->id] ?? null) === 'accepted';
        return ['data' => $this->card($p, $short, $interest) + [
            'about_full' => $p->about, 'qualification' => $p->qualification, 'partner_pref' => $p->partner_pref ?: (object) [], 'country' => $p->country,
            'contact_open' => $open, 'mobile' => $open ? $p->mobile : null, 'address' => $open ? $p->address : null,
        ]];
    }

    // ---------- interests + shortlist ----------

    public function sendInterest(Request $r, int $id)
    {
        $d = $r->validate(['message' => 'nullable|string|max:300']);
        $me = $this->acting($r);
        $to = MatrimonyProfile::withoutGlobalScopes()->where('status', 'paid')->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '>=', today()))->where('visible', true)->findOrFail($id);
        abort_if($to->id === $me->id || $to->family_id === $me->family_id, 422, 'You cannot send an interest to your own family.');
        abort_if(strcasecmp((string) $to->gender, (string) $me->gender) === 0, 422, 'You can only send an interest to a profile of the opposite gender.');
        abort_if(DB::table('matrimony_interests')->where(fn ($q) => $q->where('from_profile_id', $me->id)->where('to_profile_id', $to->id))
            ->orWhere(fn ($q) => $q->where('from_profile_id', $to->id)->where('to_profile_id', $me->id))->exists(), 422, 'An interest with this profile already exists.');
        abort_if(DB::table('matrimony_interests')->where('from_profile_id', $me->id)->whereDate('created_at', today())->count() >= 20, 429, 'Today\'s interest limit (20) is reached.');
        $i = DB::table('matrimony_interests')->insertGetId(['from_profile_id' => $me->id, 'to_profile_id' => $to->id, 'message' => $d['message'] ?? null, 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);
        if ($to->partner_id) app(\App\Services\PartnerService::class)->enquire((int) $to->partner_id, $r->user(), 'profile', $to->id, trim("{$me->name} ({$me->parinay_id}) " . ($d['message'] ?? '')));
        if ($u = $this->ownerUser($to)) app(GreetingService::class)->notify($u, 'New Parinay interest', "{$me->name} ({$me->parinay_id}) has sent an interest for {$to->name}.", ['type' => 'parinay_interest', 'id' => $i]);
        return response()->json(['ok' => true, 'message' => 'Interest sent.'], 201);
    }

    public function respond(Request $r, int $id)
    {
        $d = $r->validate(['action' => 'required|in:accept,decline']);
        $i = DB::table('matrimony_interests')->find($id);
        abort_unless($i, 404);
        $to = MatrimonyProfile::withoutGlobalScopes()->findOrFail($i->to_profile_id);
        abort_unless($this->familyProfiles($r)->whereKey($to->id)->exists(), 404);
        abort_unless(self::isPaid($to), 403, 'A profile fee (Paid) is required.');
        DB::table('matrimony_interests')->where('id', $id)->update(['status' => $d['action'] === 'accept' ? 'accepted' : 'declined', 'updated_at' => now()]);
        $from = MatrimonyProfile::withoutGlobalScopes()->find($i->from_profile_id);
        if ($from && ($u = $this->ownerUser($from))) {
            $acc = $d['action'] === 'accept';
            app(GreetingService::class)->notify($u, $acc ? 'Parinay interest accepted' : 'Parinay interest declined',
                $acc ? "{$to->name} ({$to->parinay_id}) accepted your interest. You can now see their contact." : "{$to->name} did not move the interest forward this time.", ['type' => 'parinay_interest', 'id' => $id]);
        }
        return ['ok' => true];
    }

    /** GET matrimony/interests?profile_id= : received + sent. */
    public function interests(Request $r)
    {
        $me = $this->acting($r, false);
        $rows = DB::table('matrimony_interests')->where('from_profile_id', $me->id)->orWhere('to_profile_id', $me->id)->orderByDesc('id')->limit(100)->get();
        $profiles = MatrimonyProfile::withoutGlobalScopes()->whereIn('id', $rows->flatMap(fn ($i) => [$i->from_profile_id, $i->to_profile_id])->unique())->get()->keyBy('id');
        $map = fn ($i, $otherId) => ['interest_id' => $i->id, 'status' => $i->status, 'message' => $i->message, 'at' => $i->created_at, 'profile' => isset($profiles[$otherId]) ? $this->card($profiles[$otherId]) : null];
        return [
            'received' => $rows->where('to_profile_id', $me->id)->map(fn ($i) => $map($i, $i->from_profile_id))->values(),
            'sent' => $rows->where('from_profile_id', $me->id)->map(fn ($i) => $map($i, $i->to_profile_id))->values(),
        ];
    }

    public function toggleShortlist(Request $r, int $id)
    {
        $me = $this->acting($r);
        MatrimonyProfile::withoutGlobalScopes()->where('status', 'paid')->findOrFail($id);
        $q = DB::table('matrimony_shortlists')->where('profile_id', $me->id)->where('target_id', $id);
        if ($q->exists()) { $q->delete(); return ['shortlisted' => false]; }
        abort_if(DB::table('matrimony_shortlists')->where('profile_id', $me->id)->count() >= 200, 422, 'Shortlist is full (200).');
        DB::table('matrimony_shortlists')->insert(['profile_id' => $me->id, 'target_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        return ['shortlisted' => true];
    }

    public function shortlist(Request $r)
    {
        $me = $this->acting($r);
        $ids = DB::table('matrimony_shortlists')->where('profile_id', $me->id)->orderByDesc('id')->pluck('target_id');
        $ps = MatrimonyProfile::withoutGlobalScopes()->whereIn('id', $ids)->get()->keyBy('id');
        [$short, $interest] = $this->relations($me, $ids);
        return ['data' => $ids->map(fn ($i) => $ps[$i] ?? null)->filter()->map(fn ($p) => $this->card($p, $short, $interest))->values()];
    }
}
