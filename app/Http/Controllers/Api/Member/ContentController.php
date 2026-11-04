<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{Business, CommitteeMember, Department, Donation, Event, Family, Lookup, MatrimonyProfile, Member, Office, Setting, Trust, UserNotification};
use App\Services\RazorpayService;
use App\Support\{CustomForm, Exporter, MemberApp};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB, Storage};

/** Member app: masters (dropdowns), Trusts, Downloads, Mahesh Vandana, Patrika, Mangal Parinay, Yashashwi (business), Donations, committee, notifications, legal & contact. */
class ContentController extends Controller
{
    private const DEFAULT_RELATIONS = ['AUNTY', 'BROTHER', 'BROTHER-IN-LAW', 'CHACHA (KAKAJI)', 'CHACHI (KAKIJI)', 'DAUGHTER', 'DAUGHTER OF GRANDSON', 'DAUGHTER-IN-LAW (BAHU)', 'FATHER', 'FATHER-IN-LAW (SASUR)', 'GRAND DAUGHTER (POTI)', 'GRAND DAUGHTER-IN-LAW', 'GRAND MOTHER', 'GRAND SON (POTA)', 'GRANDFATHER', 'HUSBAND', 'MOTHER', 'MOTHER-IN-LAW', 'NEPHEW (BHATIJA)', 'NEPHEWS WIFE', 'NIECE (BHATIJI)', 'OTHER', 'SISTER', 'SISTER-IN-LAW', 'SON', 'SON OF GRANDSON', 'SPOUSE', 'STEP MOTHER', 'UNCLE', 'WIFE'];

    // ---------- public (needed before login, for the registration form) ----------
    /** One call returns every dropdown. Send If-None-Match: the app caches it locally and only re-downloads when it changed. */
    /** Organisation name, brand colours and logo for the apps (public, cached for 5 minutes). */
    public function config()
    {
        return response()->json(cache()->remember('api.config', 300, fn () => [
            'org_name' => \App\Models\Setting::get('org_name', 'Akhil Bharatvarshiya Maheshwari Mahasabha'),
            'theme' => \App\Support\Theme::palette(),
            'logo_url' => asset('images/abmm-logo.jpg'),
        ]));
    }

    public function masters(Request $r)
    {
        $data = cache()->remember('api.masters', 300, function () {
            $g = Lookup::grouped();
            unset($g['tehsil'], $g['city']);
            $g['relation'] = $g['relation'] ?? self::DEFAULT_RELATIONS;
            $g['gender'] = $g['gender'] ?? ['MALE', 'FEMALE'];
            $g['country'] = $g['country'] ?? ['INDIA'];
            $g['resident_type'] = $g['resident_type'] ?? ['OWNED', 'RENTED'];
            $states = Lookup::ofType('state')->active()->orderBy('name')->get(['id', 'name']);
            $dist = Lookup::ofType('district')->active()->orderBy('name')->get(['name', 'parent_id'])->groupBy('parent_id');
            $g['state_districts'] = $states->unique('name')->mapWithKeys(fn ($s) => [$s->name => ($dist[$s->id] ?? collect())->pluck('name')->unique()->values()->all()])->all();
            $g['caste_gotras'] = Lookup::casteGotras();
            $g['country_codes'] = Lookup::countryCodes();
            $cats = Lookup::ofType('trust_category')->active()->orderBy('sort')->orderBy('name')->get(['id', 'name']);
            $names = Lookup::ofType('trust_name')->active()->orderBy('sort')->orderBy('name')->get(['name', 'parent_id', 'meta'])->groupBy('parent_id');
            $g['trust_tree'] = $cats->map(fn ($c) => ['category' => $c->name, 'trusts' => ($names[$c->id] ?? collect())->map(fn ($n) => ['name' => $n->name] + (array) ($n->meta ?? []))->values()->all()])->all();
            $g['extra_member'] = CustomForm::fields('member');
            $g['extra_family'] = CustomForm::fields('family');
            return $g;
        });
        $etag = '"' . md5(json_encode($data)) . '"';
        if ($r->header('If-None-Match') === $etag) return response('', 304)->header('ETag', $etag);
        return response()->json($data)->header('ETag', $etag)->header('Cache-Control', 'private, max-age=300');
    }

    /** Village / Town / City auto-complete (Tehsil-Town master). Returns district / state / pradesh / pincode to auto-fill. */
    public function locations(Request $r)
    {
        $q = mb_strtolower(trim((string) $r->query('q')));
        if (mb_strlen($q) < 2) return ['data' => []];
        $out = [];
        foreach (Lookup::tehsilMeta() as $name => $m) {
            if (str_contains(mb_strtolower($name), $q)) { $out[] = ['name' => $name] + $m; if (count($out) >= 30) break; }
        }
        return ['data' => $out];
    }

    public function pages(string $slug)
    {
        abort_unless(in_array($slug, ['privacy-policy', 'terms-and-conditions', 'refund-policy']), 404);
        return ['title' => ucwords(str_replace('-', ' ', $slug)), 'body' => Setting::get('page_' . str_replace('-', '_', $slug), Setting::get("page_$slug", ''))];
    }

    public function contact()
    {
        return ['offices' => Office::orderBy('sort')->get(['title', 'person', 'address', 'phones', 'email']),
            'org' => ['name' => Setting::get('org_name'), 'mobile' => Setting::get('contact_mobile'), 'email' => Setting::get('contact_email'), 'address' => Setting::get('contact_address')]];
    }

    // ---------- Mahesh Vandana / Downloads / Patrika ----------
    public function vandana()
    {
        $u = fn ($k) => ($p = Setting::get("vandana_$k")) ? Storage::disk('public')->url($p) : null;
        return ['image_url' => $u('image'), 'audio_url' => $u('audio')];
    }

    public function downloads()
    {
        return ['data' => DB::table('downloads')->where('is_active', true)->orderBy('sort')->orderBy('id')->get(['id', 'title', 'url'])];
    }

    public function patrika()
    {
        return ['patrika_url' => Setting::get('patrika_url'), 'epatrika_url' => Setting::get('epatrika_url')];
    }

    // ---------- Trusts ----------
    /** Form definition for one trust: common fields are prefilled from the profile; extra fields come from Super Admin's Form Fields. */
    public function trustForm(Request $r)
    {
        $cat = $r->query('category');
        $m = Member::withoutGlobalScopes()->findOrFail($r->user()->member_id);
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        return ['prefill' => ['name' => trim("$m->name $m->surname"), 'email' => $m->email, 'dob' => $m->dob?->toDateString(), 'contact' => $m->mobile ?: $r->user()->mobile, 'gender' => $m->gender,
            'marital_status' => $m->marital_status, 'city' => $m->city ?: $f->city, 'address' => $f->address, 'country' => $f->country, 'state' => $f->state, 'district' => $f->district, 'pincode' => $f->pincode],
            'extra_fields' => CustomForm::forCategory('trust', $cat)];
    }

    public function trustApply(Request $r)
    {
        $d = $r->validate([
            'trust_category' => 'required|string|max:120', 'trust_name' => 'required|string|max:200', 'name' => 'required|string|max:200', 'email' => 'required|email|max:160',
            'dob' => 'required|date|before:today', 'contact' => ['required', 'regex:/^[6-9]\d{9}$/'], 'gender' => 'required|string|max:20', 'marital_status' => 'required|string|max:20',
            'city' => 'required|string|max:100', 'aadhar' => ['required', 'regex:/^\d{12}$/'], 'address' => 'nullable|string|max:400', 'country' => 'nullable|string|max:60',
            'state' => 'nullable|string|max:80', 'district' => 'nullable|string|max:80', 'pincode' => ['nullable', 'regex:/^\d{6}$/'],
        ] + CustomForm::rules('trust', $r->input('trust_category')));
        $u = $r->user();
        $t = Trust::create($d + ['extra' => CustomForm::collect($r, 'trust'), 'source' => 'app', 'family_id' => $u->family_id, 'member_id' => $u->member_id, 'user_id' => $u->id]);
        UserNotification::notifyAdmins("Trust application (app): {$t->name} — {$t->trust_name}", route('admin.trusts.index'), Family::withoutGlobalScopes()->whereKey($u->family_id)->value('pradesh'));
        return response()->json(['id' => $t->id, 'message' => 'Your form has been submitted. The Mahasabha will forward it to the department.'], 201);
    }

    public function trustMine(Request $r)
    {
        return ['data' => Trust::where('user_id', $r->user()->id)->latest()->limit(50)->get(['id', 'trust_category', 'trust_name', 'name', 'created_at'])];
    }

    public function trustPdf(Request $r, int $id)
    {
        $t = Trust::where('user_id', $r->user()->id)->findOrFail($id);
        $rows = collect($t->details())->map(fn ($v, $k) => [$k, (string) $v])->values();
        return Exporter::download('pdf', 'trust-' . $t->id, $t->name, ['Field', 'Details'], $rows);
    }

    // ---------- Mangal Parinay ----------
    public function parinayMine(Request $r)
    {
        $ids = Member::withoutGlobalScopes()->where('family_id', $r->user()->family_id)->pluck('id');
        $fee = $this->parinayFee();
        return ['data' => MatrimonyProfile::whereIn('member_id', $ids)->get(['id', 'member_id', 'parinay_id', 'name', 'status', 'registered_at', 'paid_until']), 'fee' => $fee, 'fee_note' => "Mangal Parinay registration: Rs. $fee / year"];
    }

    private function parinayFee(): int { return (int) Setting::get('parinay_fee', 500); }

    /** Razorpay order for one profile of this family (registration / yearly renewal). */
    public function parinayPay(Request $r, MatrimonyProfile $profile, RazorpayService $rzp)
    {
        $this->ownParinay($r, $profile);
        abort_unless($rzp->configured(), 503, 'Payment gateway is not configured yet.');
        abort_if($profile->status === 'paid' && $profile->paid_until && $profile->paid_until->gt(now()->addDays(30)), 422, 'The fee for this profile has already been submitted.');
        try { $o = $rzp->createOrder($this->parinayFee(), 'par_' . $profile->id . '_' . time(), ['parinay_id' => $profile->parinay_id]); }
        catch (\Throwable $e) { report($e); abort(502, 'Payment could not be started, please try again shortly.'); }
        $profile->update(['razorpay_order_id' => $o['id']]);
        return ['order_id' => $o['id'], 'amount_paise' => $o['amount'], 'key_id' => config('abmm.razorpay.key_id'), 'name' => 'ABMM Mangal Parinay', 'prefill' => ['contact' => $r->user()->mobile]];
    }

    public function parinayVerify(Request $r, RazorpayService $rzp)
    {
        $d = $r->validate(['razorpay_order_id' => 'required', 'razorpay_payment_id' => 'required', 'razorpay_signature' => 'required']);
        $p = MatrimonyProfile::where('razorpay_order_id', $d['razorpay_order_id'])->firstOrFail();
        $this->ownParinay($r, $p);
        abort_unless($rzp->verifyPayment($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature']), 422, 'Payment could not be verified.');
        self::markParinayPaid($p, $d['razorpay_payment_id']);
        return ['message' => 'Fee received. Your Mangal Parinay profile is now Paid.', 'paid_until' => $p->fresh()->paid_until?->toDateString()];
    }

    /** Idempotent; also called from the Razorpay webhook. */
    public static function markParinayPaid(MatrimonyProfile $p, string $paymentId): void
    {
        if ($p->payment_ref === $paymentId && $p->status === 'paid') return;
        $base = $p->paid_until && $p->paid_until->isFuture() ? $p->paid_until : now();
        $p->update(['status' => 'paid', 'payment_ref' => $paymentId, 'paid_until' => $base->copy()->addYear()->toDateString()]);
    }

    private function ownParinay(Request $r, MatrimonyProfile $p): void
    {
        abort_unless($p->member_id && Member::withoutGlobalScopes()->whereKey($p->member_id)->where('family_id', $r->user()->family_id)->exists(), 404);
    }

    /** Register an eligible family member (from the 3-dot menu). */
    public function parinayRegister(Request $r, Member $member)
    {
        abort_unless($member->family_id === $r->user()->family_id && $member->status === 'active' && $member->approval === 'approved', 404);
        abort_unless($member->id === $r->user()->member_id || Member::withoutGlobalScopes()->whereKey($r->user()->member_id)->value('is_head'), 403, 'Only the Head of Family can do this.');
        abort_unless($member->dob && $member->dob->age >= 18, 422, 'Minimum age for Mangal Parinay is 18 years.');
        abort_if(MatrimonyProfile::where('member_id', $member->id)->exists(), 422, 'This member already has a Parinay registration.');
        $d = $r->validate(['qualification' => 'nullable|string|max:120', 'degree' => 'nullable|string|max:120', 'occupation_type' => 'nullable|string|max:60', 'occupation' => 'nullable|string|max:120',
            'height' => 'nullable|string|max:20', 'height_cm' => 'nullable|integer|min:100|max:230', 'diet' => 'nullable|string|max:20', 'manglik' => 'sometimes|boolean', 'about' => 'nullable|string|max:1000']);
        if (! empty($d['height_cm'])) { $in = (int) round($d['height_cm'] / 2.54); $d['height'] = intdiv($in, 12) . "'" . ($in % 12) . '"'; }
        $f = Family::withoutGlobalScopes()->findOrFail($member->family_id);
        abort_if(app(\App\Services\FraudService::class)->blocked(['mobile' => $member->mobile ?: $f->mobile, 'name' => $member->name]), 422, 'Registration is not allowed with this mobile / name. Contact the admin.');
        do { $pid = (string) random_int(1000000000, 9999999999); } while (MatrimonyProfile::where('parinay_id', $pid)->exists());
        $p = MatrimonyProfile::create($d + ['parinay_id' => $pid, 'family_id' => $f->id, 'member_id' => $member->id, 'name' => trim("$member->name $member->surname"), 'gender' => ucfirst(strtolower((string) $member->gender)),
            'dob' => $member->dob, 'mobile' => $member->mobile ?: $f->mobile, 'address' => $member->address ?: $f->address, 'state' => $f->state, 'district' => $f->district, 'pincode' => $f->pincode,
            'qualification' => $d['qualification'] ?? $member->education, 'occupation' => $d['occupation'] ?? $member->occupation, 'gotra' => $member->gotra ?: $f->gotra,
            'marital_status' => ['UNMARRIED' => 'Never Married', 'DIVORCEE' => 'Divorced', 'WIDOW' => 'Widowed', 'WIDOWER' => 'Widowed'][strtoupper((string) $member->marital_status)] ?? null,
            'city' => $member->city ?: $f->city, 'country' => $f->country ?: 'INDIA', 'photo' => $member->photo, 'status' => 'registered', 'registered_at' => now(), 'member_code' => $member->member_code]);
        app(\App\Services\FraudService::class)->scan($p);          // duplicate / shared-mobile / underage flags for the admin queue
        return response()->json(['id' => $p->id, 'parinay_id' => $p->parinay_id, 'fee' => $this->parinayFee(), 'message' => "Registered with Parinay ID {$p->parinay_id}. Pay the fee to make the profile Paid."], 201);
    }

    // ---------- Yashashwi (business) ----------
    public function businesses(Request $r)
    {
        $q = Business::where('status', 'active')->when($r->query('q'), fn ($w, $t) => $w->where(fn ($x) => $x->where('name', 'like', "%$t%")->orWhere('type', 'like', "%$t%")))->when($r->query('type'), fn ($w, $t) => $w->where('type', $t));
        $page = $q->orderBy('id', 'desc')->cursorPaginate(30, ['id', 'name', 'type', 'address', 'mobile', 'email', 'image', 'description']);
        return ['data' => collect($page->items())->map(fn ($b) => $b->toArray() + ['image_url' => MemberApp::photoUrl($b->image)]), 'next_cursor' => $page->nextCursor()?->encode()];
    }

    public function businessStore(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'type' => 'required|string|max:120', 'address' => 'required|string|max:400', 'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => 'nullable|email|max:120', 'description' => 'nullable|string|max:1000', 'image' => 'nullable|image|max:3072']);
        if ($r->hasFile('image')) $d['image'] = MemberApp::storePhoto($r->file('image'), 'business');
        $b = Business::create($d + ['family_id' => $r->user()->family_id, 'status' => 'pending']);
        return response()->json(['id' => $b->id, 'message' => 'Business submitted. It will appear after approval.'], 201);
    }

    // ---------- Referral ----------
    public function referral(Request $r)
    {
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $list = Family::withoutGlobalScopes()->where('referred_by', $f->id)->orderByDesc('id')->limit(100)->get(['head_name', 'surname', 'app_registered_at']);
        return ['code' => $f->family_code, 'count' => Family::withoutGlobalScopes()->where('referred_by', $f->id)->count(),
            'share_text' => "Download the ABMM Sampark app and enter Referral Code {$f->family_code} while registering. Join the Mahasabha!",
            'data' => $list->map(fn ($x) => ['name' => trim("$x->head_name $x->surname"), 'date' => $x->app_registered_at?->toDateString()])];
    }

    // ---------- Donations ----------
    public function donate(Request $r, RazorpayService $rzp)
    {
        $d = $r->validate(['amount' => 'required|numeric|min:10|max:500000', 'pan' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'], 'email' => 'nullable|email', 'city' => 'nullable|string|max:80']);
        abort_unless($rzp->configured(), 503, 'Payment gateway is not configured yet.');
        $m = Member::withoutGlobalScopes()->findOrFail($r->user()->member_id);
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $don = Donation::create(['name' => trim("$m->name $m->surname"), 'family_code' => $f->family_code, 'amount' => $d['amount'], 'email' => $d['email'] ?? ($m->email ?: $f->email ?: 'na@abmm.local'),
            'mobile' => $r->user()->mobile, 'city' => $d['city'] ?? $f->city, 'pan_enc' => ! empty($d['pan']) ? Crypt::encryptString(strtoupper($d['pan'])) : null, 'status' => 'pending']);
        try { $order = $rzp->createOrder($don->amount, 'don_' . $don->id, ['donation_id' => (string) $don->id]); }
        catch (\Throwable $e) { report($e); $don->update(['status' => 'failed']); abort(502, 'Payment could not be started, please try again shortly.'); }
        $don->update(['razorpay_order_id' => $order['id']]);
        return ['donation_id' => $don->id, 'order_id' => $order['id'], 'amount_paise' => $order['amount'], 'key_id' => config('abmm.razorpay.key_id'), 'name' => 'ABMM Sampark', 'prefill' => ['contact' => $don->mobile, 'email' => $don->email]];
    }

    public function donateVerify(Request $r, RazorpayService $rzp)
    {
        $d = $r->validate(['razorpay_order_id' => 'required', 'razorpay_payment_id' => 'required', 'razorpay_signature' => 'required']);
        $don = Donation::where('razorpay_order_id', $d['razorpay_order_id'])->where('mobile', $r->user()->mobile)->firstOrFail();
        if (! $rzp->verifyPayment($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) { $don->update(['status' => 'failed']); abort(422, 'Payment could not be verified.'); }
        $don->update(['status' => 'success', 'razorpay_payment_id' => $d['razorpay_payment_id'], 'payment_ref' => $d['razorpay_payment_id'], 'paid_at' => now()]);
        UserNotification::notifyAdmins("Donation ₹{$don->amount} — {$don->name} (app)", route('admin.donations.index'));
        return ['message' => "Thank you! Your donation of ₹{$don->amount} was received.", 'payment_id' => $d['razorpay_payment_id']];
    }

    public function donations(Request $r)
    {
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        return ['data' => Donation::where('family_code', $f->family_code)->where('status', 'success')->latest('paid_at')->limit(100)->get(['id', 'amount', 'payment_ref', 'paid_at', 'created_at'])];
    }

    // ---------- District committee / meetings / notifications ----------
    public function committee(Request $r)
    {
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $rows = CommitteeMember::withoutGlobalScopes()->where('is_active', true)->where(fn ($q) => $q->where('level', 'country')
            ->orWhere(fn ($w) => $w->where('level', 'pradesh')->where('pradesh', $f->pradesh))
            ->orWhere(fn ($w) => $w->whereIn('level', ['state', 'district', 'tehsil'])->where('pradesh', $f->pradesh)->where('district', $f->district)))
            ->orderBy('level')->orderBy('designation')->get(['id', 'name', 'surname', 'designation', 'level', 'mobile', 'district', 'photo']);
        return ['data' => $rows->map(fn ($c) => $c->toArray() + ['photo_url' => MemberApp::photoUrl($c->photo)])];
    }

    public function notifications(Request $r)
    {
        $fid = $r->user()->family_id;
        $rows = DB::table('notification_recipients as nr')->join('notifications_log as n', 'n.id', '=', 'nr.notification_id')->where('nr.family_id', $fid)
            ->orderByDesc('n.id')->limit(50)->get(['n.id', 'n.title', 'n.message', 'n.kind', 'n.created_at', 'nr.seen_at']);
        return ['data' => $rows, 'unread' => $rows->whereNull('seen_at')->count()];
    }

    public function notificationSeen(Request $r, int $id)
    {
        DB::table('notification_recipients')->where('notification_id', $id)->where('family_id', $r->user()->family_id)->whereNull('seen_at')->update(['seen_at' => now()]);
        DB::table('notifications_log')->where('id', $id)->update(['seen_count' => DB::table('notification_recipients')->where('notification_id', $id)->whereNotNull('seen_at')->count()]);
        return response()->noContent();
    }

    public function meetings(Request $r)
    {
        $ids = DB::table('notification_recipients as nr')->join('notifications_log as n', 'n.id', '=', 'nr.notification_id')->where('nr.family_id', $r->user()->family_id)->where('n.kind', 'meeting')->orderByDesc('n.id')->limit(30)->get(['n.id', 'n.title', 'n.message', 'n.created_at']);
        return ['data' => $ids];
    }

    public function topics(Request $r)
    {
        $f = Family::withoutGlobalScopes()->findOrFail($r->user()->family_id);
        $slug = fn ($v) => \Illuminate\Support\Str::slug((string) $v);
        return ['topics' => array_values(array_filter(['all', $f->pradesh ? 'pradesh_' . $slug($f->pradesh) : null, $f->state ? 'state_' . $slug($f->state) : null,
            $f->district ? 'district_' . $slug($f->district) : null, $f->tehsil ? 'tehsil_' . $slug($f->tehsil) : null]))];
    }

    public function userNotifications(Request $r)
    {
        $q = UserNotification::where('user_id', $r->user()->id);
        $rows = (clone $q)->latest()->limit(30)->get(['id', 'title', 'read_at', 'created_at']);
        (clone $q)->whereNull('read_at')->update(['read_at' => now()]);
        return ['data' => $rows];
    }
}
