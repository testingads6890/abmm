<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{ApiToken, Family, Member, User, UserNotification};
use App\Services\FirebaseIdToken;
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB, Hash, RateLimiter};
use Illuminate\Support\Str;

/** Member app: OTP login (Firebase Phone Auth, or the TEST_OTP shortcut), registration as Head of Family / Member, session. */
class AuthController extends Controller
{
    /** Step 1+2 of the app: mobile + OTP. Returns a token for known numbers, otherwise a signup_token to register. */
    public function verify(Request $r, FirebaseIdToken $fb)
    {
        $d = $r->validate(['mobile' => ['nullable', 'regex:/^\d{6,14}$/'], 'country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'], 'otp' => 'nullable|digits:6', 'id_token' => 'nullable|string|max:4000', 'device_name' => 'nullable|string|max:100', 'fcm_token' => 'nullable|string|max:255']);
        $key = 'm-verify:' . ($d['mobile'] ?? 'fb') . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) return response()->json(['message' => 'Too many attempts. ' . RateLimiter::availableIn($key) . ' sec.'], 429);

        $phone = null;
        // International numbers: national digits + country_code (default +91, which keeps the strict Indian 10-digit rule).
        if (! empty($d['mobile']) && ! \App\Services\PhoneService::valid($d['mobile'], $d['country_code'] ?? '+91'))
            return response()->json(['message' => 'Enter a valid mobile number.'], 422);
        $test = (string) config('abmm.test_otp');
        if ($test !== '' && ! empty($d['mobile']) && ! empty($d['otp']) && hash_equals($test, (string) $d['otp'])) $phone = $d['mobile'];
        elseif (! empty($d['id_token'])) $phone = $fb->verifyPhone($d['id_token'])['phone'] ?? null;
        if (! $phone) { RateLimiter::hit($key, 300); return response()->json(['message' => 'The OTP is wrong or has expired.'], 422); }
        RateLimiter::clear($key);

        $u = User::where('role', 'member')->where('mobile', $phone)->first() ?? $this->linkExisting($phone);
        if (! $u) return ['registered' => false, 'mobile' => $phone, 'signup_token' => Crypt::encryptString(json_encode(['m' => $phone, 'e' => now()->addMinutes(30)->timestamp]))];
        return $this->session($u, $d['device_name'] ?? null, $d['fcm_token'] ?? null);
    }

    /** A number already present in a family (added by admin or by the head) logs straight in – no second registration. */
    private function linkExisting(string $phone): ?User
    {
        $m = Member::withoutGlobalScopes()->where('mobile', $phone)->where('status', 'active')->where('approval', 'approved')->orderByDesc('is_head')->orderBy('id')->first();
        if (! $m) return null;
        return $this->makeUser($m, $phone);
    }

    private function makeUser(Member $m, string $phone): User
    {
        $u = new User(['name' => trim("$m->surname $m->name"), 'email' => "$phone@member.abmm.local", 'mobile' => $phone, 'password' => Str::random(40), 'is_active' => true]);
        $u->role = 'member'; $u->family_id = $m->family_id; $u->member_id = $m->id;
        $u->save();
        return $u;
    }

    private function session(User $u, ?string $device, ?string $fcm): array
    {
        [, $token] = ApiToken::issue($u, $device, $fcm, 90);
        $u->forceFill(['last_login_at' => now()])->save();
        return ['registered' => true, 'token' => $token, 'user' => self::payload($u)];
    }

    public static function payload(User $u): array
    {
        $m = $u->member_id ? Member::withoutGlobalScopes()->find($u->member_id) : null;
        $f = $u->family_id ? Family::withoutGlobalScopes()->find($u->family_id) : null;
        return ['id' => $u->id, 'name' => $u->name, 'mobile' => $u->mobile, 'state' => $m?->approval ?? 'none', 'is_head' => (bool) $m?->is_head,
            'member' => $m ? MemberApp::member($m) : null, 'family' => $f ? MemberApp::family($f) : null];
    }

    public function me(Request $r) { return ['user' => self::payload($r->user())]; }

    private function signup(Request $r): string
    {
        try { $p = json_decode(Crypt::decryptString((string) $r->input('signup_token')), true); } catch (\Throwable) { $p = null; }
        abort_unless($p && ($p['e'] ?? 0) > time(), 422, 'The OTP session has expired, please verify again.');
        abort_if(User::where('role', 'member')->where('mobile', $p['m'])->exists(), 422, 'This mobile is already registered.');
        return $p['m'];
    }

    /** Register as Head of Family: creates the family + the head member. */
    public function registerHead(Request $r)
    {
        $phone = $this->signup($r);
        $d = $r->validate(['signup_token' => 'required', 'referral_code' => 'nullable|string|max:12'] + MemberApp::rules() + \App\Support\CustomForm::rules('family'));
        $referrer = ! empty($d['referral_code']) ? Family::withoutGlobalScopes()->where('family_code', $d['referral_code'])->first() : null;
        abort_if(! empty($d['referral_code']) && ! $referrer, 422, 'The referral code is wrong.');
        $d = MemberApp::location($d);
        unset($d['signup_token'], $d['relation']);
        $d['mobile'] = $phone; $d['country'] = $d['country'] ?? 'INDIA'; $d['city'] = $d['city'] ?? ''; $d['address'] = $d['address'] ?? '';

        $u = DB::transaction(function () use ($r, $d, $referrer) {
            $f = new Family;
            $f->referred_by = $referrer?->id;
            $f->family_code = Family::nextCode(); $f->source = 'app'; $f->app_registered_at = now(); $f->member_count = 1; $f->status = 'active';
            foreach (MemberApp::FAMILY_COLS as $c) if (array_key_exists($c, $d)) $f->{$c} = $d[$c];
            $f->head_name = $d['name'];
            $f->extra = \App\Support\CustomForm::collect($r, 'family');
            if ($r->hasFile('photo')) $f->photo = MemberApp::storePhoto($r->file('photo'), 'families');
            $f->save();
            $f->syncHead();
            $m = Member::withoutGlobalScopes()->where('family_id', $f->id)->where('is_head', true)->first();
            $m->update(['source' => 'app', 'approval' => 'approved']);
            UserNotification::notifyAdmins("App registration: {$f->head_name} {$f->surname} ({$f->district})", null, $f->pradesh);
            return $this->makeUser($m, $d['mobile']);
        });
        if ($referrer) {   // referral reward for the referring family's head
            try {
                $ru = \App\Models\User::withoutGlobalScopes()->where('family_id', $referrer->id)->where('role', 'member')->orderBy('id')->first();
                if ($ru) app(\App\Services\PointsService::class)->award($ru, 'referral', (string) $u->family_id);
            } catch (\Throwable $e) { report($e); }
        }
        return $this->session($u, $r->input('device_name'), $r->input('fcm_token')) + ['message' => 'Registered.'];
    }

    /** Register as a Member of an existing family: needs the Family ID or the head's mobile; the head approves in the app. */
    public function registerMember(Request $r)
    {
        $phone = $this->signup($r);
        $d = $r->validate(['signup_token' => 'required', 'family_code' => 'nullable|string|max:12', 'head_mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'], 'relation' => 'required|string|max:40'] + MemberApp::rules() + \App\Support\CustomForm::rules('member'));
        abort_unless($d['family_code'] ?? $d['head_mobile'] ?? null, 422, 'Enter the Family ID or the Head mobile number.');
        $f = Family::withoutGlobalScopes()->where('status', 'active')->when($d['family_code'] ?? null, fn ($q, $c) => $q->where('family_code', $c), fn ($q) => $q->where('mobile', $d['head_mobile']))->first();
        abort_unless($f, 422, 'Family not found. Please check the details.');

        $d = MemberApp::location($d);
        $u = DB::transaction(function () use ($r, $d, $f, $phone) {
            $m = new Member;
            foreach (MemberApp::MEMBER_COLS as $c) if (array_key_exists($c, $d)) $m->{$c} = $d[$c];
            $m->extra = \App\Support\CustomForm::collect($r, 'member');
            $m->family_id = $f->id; $m->mobile = $phone; $m->source = 'app'; $m->approval = 'pending'; $m->status = 'active'; $m->is_head = false;
            if ($r->hasFile('photo')) $m->photo = MemberApp::storePhoto($r->file('photo'), 'members');
            $m->save();
            $u = $this->makeUser($m, $phone);
            $m->update(['requested_by' => $u->id]);
            return $u;
        });
        if ($head = User::where('role', 'member')->where('family_id', $f->id)->whereIn('member_id', Member::withoutGlobalScopes()->where('family_id', $f->id)->where('is_head', true)->select('id'))->first()) {
            UserNotification::create(['user_id' => $head->id, 'title' => "{$d['name']} is requesting to join your family"]);
        }
        return $this->session($u, $r->input('device_name'), $r->input('fcm_token')) + ['message' => 'The request has been sent to the Head of Family.'];
    }

    public function fcm(Request $r)
    {
        $d = $r->validate(['fcm_token' => 'required|string|max:255']);
        $r->attributes->get('api_token')->update(['fcm_token' => $d['fcm_token']]);
        return response()->noContent();
    }

    public function logout(Request $r)
    {
        $r->attributes->get('api_token')->delete();
        return response()->noContent();
    }
}
