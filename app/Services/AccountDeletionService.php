<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;

/**
 * Member account deletion. Request = login blocked at once, tokens revoked, hidden from directories; after GRACE_DAYS (or when an admin presses
 * "Erase now") personal data is erased. Financial records (payments, receipts, 80G donations) are kept - tax law requires it - but are no longer
 * linked to a readable name. The rest of the family and its other members are NOT touched.
 */
class AccountDeletionService
{
    public const GRACE_DAYS = 30;
    /** personal profile tables keyed by user_id that are deleted outright */
    private const PROFILE_TABLES = ['blood_donors', 'mentor_profiles', 'mentee_profiles', 'investor_profiles', 'job_profiles', 'job_alerts', 'learner_profiles', 'volunteers', 'user_notifications', 'point_ledger', 'matrimony_profiles'];

    public function request(User $u, ?string $reason = null): int
    {
        abort_unless($u->role === 'member', 403, 'Staff accounts are removed by the Super Admin.');
        if ($open = DB::table('account_deletions')->where(['user_id' => $u->id, 'status' => 'requested'])->value('id')) return $open;
        $id = DB::table('account_deletions')->insertGetId(['user_id' => $u->id, 'family_id' => $u->family_id, 'reason' => $reason ? Str::limit($reason, 280, '') : null, 'status' => 'requested',
            'requested_at' => now(), 'erase_after' => now()->addDays(self::GRACE_DAYS), 'created_at' => now(), 'updated_at' => now()]);
        $u->forceFill(['is_active' => false])->save();
        DB::table('api_tokens')->where('user_id', $u->id)->delete();
        if ($u->member_id) DB::table('members')->where('id', $u->member_id)->update(['dir_hidden' => true, 'updated_at' => now()]);
        return $id;
    }

    /** Admin: the member changed their mind within the grace period. */
    public function cancel(int $id): void
    {
        $d = DB::table('account_deletions')->where('id', $id)->where('status', '!=', 'erased')->first(); if (! $d) return;     // the admin action may already have set the status
        DB::table('users')->where('id', $d->user_id)->update(['is_active' => true]);
        if ($mid = DB::table('users')->where('id', $d->user_id)->value('member_id')) DB::table('members')->where('id', $mid)->update(['dir_hidden' => false, 'updated_at' => now()]);
        DB::table('account_deletions')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    public function erase(int $id): void
    {
        $d = DB::table('account_deletions')->where(['id' => $id, 'status' => 'requested'])->first(); if (! $d) return;
        DB::transaction(function () use ($d) {
            $u = DB::table('users')->find($d->user_id);
            if ($u && $u->role === 'member') {
                if ($u->member_id && ($m = DB::table('members')->find($u->member_id))) {
                    if ($m->photo) Storage::disk('public')->delete($m->photo);
                    $upd = ['name' => 'Deleted member', 'surname' => null, 'mobile' => null, 'email' => null, 'dob' => null, 'aadhar_enc' => null, 'photo' => null, 'address' => null,
                        'father_husband_name' => null, 'mother_name' => null, 'anniversary' => null, 'extra' => null, 'dir_hidden' => true, 'status' => 'archived', 'archive_type' => 'deleted', 'archived_at' => now(), 'updated_at' => now()];
                    DB::table('members')->where('id', $m->id)->update(array_intersect_key($upd, array_flip(Schema::getColumnListing('members'))));
                }
                foreach (self::PROFILE_TABLES as $t) if (Schema::hasTable($t) && Schema::hasColumn($t, 'user_id')) DB::table($t)->where('user_id', $u->id)->delete();
                DB::table('api_tokens')->where('user_id', $u->id)->delete();
                DB::table('users')->where('id', $u->id)->update(['name' => 'Deleted member', 'email' => "deleted-{$u->id}@member.abmm.local", 'mobile' => null, 'address' => null, 'password' => bcrypt(Str::random(40)), 'is_active' => false, 'updated_at' => now()]);
            }
            DB::table('account_deletions')->where('id', $d->id)->update(['status' => 'erased', 'erased_at' => now(), 'reason' => null, 'updated_at' => now()]);
        });
    }

    /** Scheduler: erase everything whose grace period is over. */
    public function eraseDue(): int
    {
        $n = 0;
        foreach (DB::table('account_deletions')->where('status', 'requested')->where('erase_after', '<=', now())->pluck('id') as $id) { $this->erase($id); $n++; }
        return $n;
    }
}
