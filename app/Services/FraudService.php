<?php

namespace App\Services;

use App\Models\{MatrimonyProfile, UserNotification};
use Illuminate\Support\Facades\DB;

/** Duplicate / fraud checks and scam-profile removal for Parinay profiles. Flags go to the admin queue; nothing is removed automatically except hiding a profile after 3 reports. */
class FraudService
{
    public const REASONS = ['fake' => 'Fake / wrong details', 'married' => 'Already married', 'scam' => 'Scam / asking money', 'abusive' => 'Abusive behaviour', 'duplicate' => 'Duplicate profile', 'other' => 'Other'];

    public static function digits(?string $m): string { return substr(preg_replace('/\D/', '', (string) $m), -10); }

    /** Existing profiles that look like the same person (same mobile, or same date of birth and a very similar name). */
    public function duplicates(array $f, ?int $exceptProfile = null, ?int $exceptFamily = null)
    {
        $mobile = self::digits($f['mobile'] ?? null); $name = mb_strtolower(trim((string) ($f['name'] ?? ''))); $dob = $f['dob'] ?? null;
        $rows = MatrimonyProfile::withoutGlobalScopes()->where('status', '!=', 'removed')->when($exceptProfile, fn ($q, $v) => $q->where('id', '!=', $v))
            ->where(function ($q) use ($mobile, $dob) {
                if ($mobile) $q->orWhere('mobile', 'like', '%' . $mobile);
                if ($dob) $q->orWhere('dob', $dob);
            })->limit(50)->get();
        return $rows->filter(function ($p) use ($mobile, $name, $dob, $exceptFamily) {
            if ($exceptFamily && $p->family_id === $exceptFamily) return false;
            similar_text($name, mb_strtolower((string) $p->name), $pct);
            $sameMobile = $mobile && self::digits($p->mobile) === $mobile;
            return ($sameMobile && $pct >= 55) || ($dob && (string) $p->dob?->toDateString() === (string) $dob && $pct >= 80);
        })->values();
    }

    public function blocked(array $f): ?string
    {
        if (empty($f['mobile']) && empty($f['email']) && empty($f['name'])) return null;
        $q = DB::table('fraud_blocklist');
        $hit = $q->where(function ($w) use ($f) {
            if (! empty($f['mobile'])) $w->orWhere(fn ($x) => $x->where('kind', 'mobile')->where('value', self::digits($f['mobile'])));
            if (! empty($f['email'])) $w->orWhere(fn ($x) => $x->where('kind', 'email')->where('value', mb_strtolower($f['email'])));
            if (! empty($f['name'])) $w->orWhere(fn ($x) => $x->where('kind', 'name')->where('value', mb_strtolower(trim($f['name']))));
        })->first();
        return $hit ? ($hit->reason ?: "blocklisted {$hit->kind}") : null;
    }

    /** Runs every check on a stored profile and files open flags (once per type). @return string[] flag types raised */
    public function scan(MatrimonyProfile $p): array
    {
        $raised = [];
        $flag = function (string $type, string $detail) use ($p, &$raised) {
            if (DB::table('profile_flags')->where(['profile_id' => $p->id, 'type' => $type, 'status' => 'open'])->exists()) return;
            DB::table('profile_flags')->insert(['profile_id' => $p->id, 'type' => $type, 'detail' => mb_substr($detail, 0, 290), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $raised[] = $type;
        };
        if ($why = $this->blocked(['mobile' => $p->mobile, 'name' => $p->name])) $flag('blocklist', $why);
        if ($p->dob && $p->dob->age < 18) $flag('underage', 'Age ' . $p->dob->age);
        $dups = $this->duplicates($p->only(['mobile', 'name', 'dob']), $p->id, $p->family_id);
        if ($dups->isNotEmpty()) $flag('duplicate', 'Looks like profile ' . $dups->pluck('parinay_id')->implode(', '));
        $same = MatrimonyProfile::withoutGlobalScopes()->where('status', '!=', 'removed')->where('id', '!=', $p->id)->where('mobile', 'like', '%' . self::digits($p->mobile))->pluck('family_id')->unique();
        if (self::digits($p->mobile) && $same->count() >= 3) $flag('shared_mobile', 'Same mobile on ' . ($same->count() + 1) . ' profiles of different families');
        if (DB::table('profile_reports')->where('profile_id', $p->id)->where('status', 'open')->distinct('reporter_id')->count('reporter_id') >= 3) $flag('reports', '3 or more members reported this profile');
        $sent = DB::table('matrimony_interests')->where('from_profile_id', $p->id)->where('created_at', '>=', now()->subHour())->count();
        if ($sent >= 15) $flag('rapid_interests', "$sent interests in one hour");
        return $raised;
    }

    public function report(MatrimonyProfile $target, int $reporterUserId, string $reason, ?string $detail): void
    {
        DB::table('profile_reports')->insert(['profile_id' => $target->id, 'reporter_id' => $reporterUserId, 'reason' => $reason, 'detail' => $detail, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $this->scan($target);
        if (DB::table('profile_reports')->where('profile_id', $target->id)->where('status', 'open')->distinct('reporter_id')->count('reporter_id') >= 3) $target->update(['visible' => false]);   // hidden until an admin reviews it
    }

    /** Removes a scam / fake profile from every search and listing and closes its flags and reports. */
    public function remove(int $profileId, string $reason, ?int $adminId = null, bool $blockMobile = false): bool
    {
        $p = MatrimonyProfile::withoutGlobalScopes()->find($profileId);
        if (! $p) return false;
        $p->forceFill(['status' => 'removed', 'visible' => false, 'removed_at' => now(), 'removal_reason' => mb_substr($reason, 0, 190)])->save();
        DB::table('profile_flags')->where('profile_id', $p->id)->where('status', 'open')->update(['status' => 'removed', 'handled_by' => $adminId, 'updated_at' => now()]);
        DB::table('profile_reports')->where('profile_id', $p->id)->where('status', 'open')->update(['status' => 'actioned', 'handled_by' => $adminId, 'updated_at' => now()]);
        if ($blockMobile && self::digits($p->mobile)) DB::table('fraud_blocklist')->updateOrInsert(['kind' => 'mobile', 'value' => self::digits($p->mobile)], ['reason' => 'Removed profile ' . $p->parinay_id . ': ' . $reason, 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now()]);
        $owner = $p->member_id ? DB::table('users')->where('member_id', $p->member_id)->value('id') : ($p->partner_id ? DB::table('partners')->where('id', $p->partner_id)->value('user_id') : null);
        if ($owner) UserNotification::create(['user_id' => $owner, 'title' => "Profile {$p->parinay_id} has been removed: $reason"]);
        return true;
    }
}
