<?php

namespace App\Services;

use App\Models\{User, UserNotification};
use App\Support\Area;
use Illuminate\Support\Facades\DB;

/**
 * Milestone E - blood donors: opt-in register, search by group + city, emergency requests that alert eligible donors,
 * consent based contact (donor must approve before the requester sees the phone), donation drives (camps) and re-eligibility reminders.
 */
class BloodService
{
    public const GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    /** recipient group => donor groups that can give to it */
    public const COMPATIBLE = ['O-' => ['O-'], 'O+' => ['O+', 'O-'], 'A-' => ['A-', 'O-'], 'A+' => ['A+', 'A-', 'O+', 'O-'], 'B-' => ['B-', 'O-'], 'B+' => ['B+', 'B-', 'O+', 'O-'],
        'AB-' => ['AB-', 'A-', 'B-', 'O-'], 'AB+' => ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-']];

    public function gapDays(): int { return (int) config('abmm.blood.gap_days', 90); }

    private function tell(?int $uid, string $title, string $body, array $data = []): void
    {
        if (! $uid || ! ($u = User::withoutGlobalScopes()->find($uid))) return;
        try { app(GreetingService::class)->notify($u, $title, $body, $data); } catch (\Throwable $e) { report($e); UserNotification::create(['user_id' => $uid, 'title' => "$title: $body"]); }
    }

    public function eligibleOn(?object $d): ?string
    {
        return $d && $d->last_donated_on ? \Carbon\Carbon::parse($d->last_donated_on)->addDays($this->gapDays())->toDateString() : null;
    }

    public function isEligible(object $d): bool
    {
        $on = $this->eligibleOn($d);
        return $d->status === 'active' && $d->available && (! $on || $on <= today()->toDateString());
    }

    public function register(User $u, array $d): int
    {
        $a = Area::of($u);
        $row = array_merge(['blood_group' => $d['blood_group']], array_intersect_key($d, array_flip(['last_donated_on', 'available', 'emergency_ok', 'pincode', 'note'])),
            ['city' => $d['city'] ?? $a['city'], 'district' => $d['district'] ?? $a['district'], 'pradesh' => $a['pradesh'], 'country' => $a['country'], 'zone' => $a['zone'], 'family_id' => $a['family_id'], 'updated_at' => now()]);
        $ex = DB::table('blood_donors')->where('user_id', $u->id)->first();
        if ($ex) { DB::table('blood_donors')->where('id', $ex->id)->update($row); return $ex->id; }
        return DB::table('blood_donors')->insertGetId($row + ['user_id' => $u->id, 'status' => 'active', 'created_at' => now()]);
    }

    private function firstName(int $uid): string
    {
        $n = trim((string) DB::table('users')->where('id', $uid)->value('name'));
        $p = preg_split('/\s+/', $n) ?: [];
        return $p ? $p[0] . (isset($p[1]) ? ' ' . mb_substr($p[1], 0, 1) . '.' : '') : 'Donor';
    }

    /** Donor card as the viewer may see it (phone only after consent). */
    public function card(object $d, int $viewerId): array
    {
        $link = DB::table('blood_links')->where(['donor_user_id' => $d->user_id, 'requester_user_id' => $viewerId])->whereIn('status', ['pending', 'approved', 'donated'])->latest('id')->first();
        $ok = $link && in_array($link->status, ['approved', 'donated'], true);
        return ['donor_id' => $d->id, 'name' => $this->firstName($d->user_id), 'blood_group' => $d->blood_group, 'city' => $d->city, 'district' => $d->district, 'pradesh' => $d->pradesh,
            'eligible' => $this->isEligible($d), 'eligible_on' => $this->eligibleOn($d), 'last_donated_on' => $d->last_donated_on, 'donations_count' => $d->donations_count, 'emergency_ok' => (bool) $d->emergency_ok,
            'contact_status' => $link->status ?? 'none', 'phone' => $ok ? DB::table('users')->where('id', $d->user_id)->value('mobile') : null];
    }

    public function search(User $u, ?string $group, ?string $city, bool $compatible = false, bool $onlyEligible = true, ?string $pradesh = null)
    {
        $groups = $group ? ($compatible ? (self::COMPATIBLE[$group] ?? [$group]) : [$group]) : null;
        $rows = DB::table('blood_donors')->where('status', 'active')->where('available', true)->where('user_id', '!=', $u->id)
            ->when($groups, fn ($q) => $q->whereIn('blood_group', $groups))
            ->when($city, fn ($q, $c) => $q->where('city', 'like', str_replace(['%', '_'], ['\%', '\_'], trim($c)) . '%'))
            ->when($pradesh, fn ($q, $p) => $q->where('pradesh', $p))
            ->orderBy('donations_count', 'desc')->limit(100)->get();
        if ($onlyEligible) $rows = $rows->filter(fn ($d) => $this->isEligible($d))->values();
        return $rows->map(fn ($d) => $this->card($d, $u->id));
    }

    public function requestContact(User $u, object $donor, ?string $msg, ?int $requestId = null): int
    {
        abort_if($donor->user_id === $u->id, 422, 'This is you.');
        abort_unless($donor->status === 'active' && $donor->available, 422, 'This donor is not available right now.');
        abort_if(DB::table('blood_links')->where(['donor_user_id' => $donor->user_id, 'requester_user_id' => $u->id])->whereIn('status', ['pending', 'approved'])->exists(), 422, 'A request has already been sent to this donor.');
        $id = DB::table('blood_links')->insertGetId(['donor_user_id' => $donor->user_id, 'requester_user_id' => $u->id, 'request_id' => $requestId, 'message' => $msg, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->tell($donor->user_id, 'Blood donation request', "{$u->name} needs {$donor->blood_group} blood. Approve in the app to share your contact.", ['type' => 'blood_link', 'id' => $id]);
        return $id;
    }

    public function respond(User $donorUser, object $link, bool $accept): void
    {
        abort_unless($link->donor_user_id === $donorUser->id, 403);
        abort_unless($link->status === 'pending', 422, 'No further action is possible on this request.');
        DB::table('blood_links')->where('id', $link->id)->update(['status' => $accept ? 'approved' : 'declined', 'updated_at' => now()]);
        $this->tell($link->requester_user_id, $accept ? 'Donor ne haan kaha' : 'Donor not available', $accept ? "{$this->firstName($donorUser->id)} has allowed contact sharing. You can now see their number." : 'This donor has declined for now. Try other donors.');
    }

    /** Requester confirms that the donor gave blood. Updates donor history and closes the request when units are met. */
    public function markDonated(User $by, object $link): void
    {
        abort_unless($link->requester_user_id === $by->id || $by->isAdmin(), 403);
        abort_unless($link->status === 'approved', 422, 'The donor must be approved first.');
        DB::table('blood_links')->where('id', $link->id)->update(['status' => 'donated', 'updated_at' => now()]);
        $this->recordDonation($link->donor_user_id);
        if ($link->request_id && ($r = DB::table('blood_requests')->find($link->request_id))) {
            $done = DB::table('blood_links')->where(['request_id' => $r->id, 'status' => 'donated'])->count();
            if ($done >= $r->units) DB::table('blood_requests')->where('id', $r->id)->update(['status' => 'fulfilled', 'updated_at' => now()]);
        }
        $this->tell($link->donor_user_id, 'Thank you!', 'Thank you for donating blood. Your next donation is due ' . $this->eligibleOn(DB::table('blood_donors')->where('user_id', $link->donor_user_id)->first()) . ' later.');
    }

    public function recordDonation(int $userId, ?string $on = null): void
    {
        DB::table('blood_donors')->where('user_id', $userId)->update(['last_donated_on' => $on ?: today()->toDateString(), 'donations_count' => DB::raw('donations_count + 1'), 'updated_at' => now()]);
    }

    /** Emergency / normal request. Alerts eligible compatible donors: same city first, then district, then state (emergency only). */
    public function createRequest(User $u, array $d): array
    {
        $a = Area::of($u);
        $id = DB::table('blood_requests')->insertGetId(array_merge(array_intersect_key($d, array_flip(['patient_name', 'blood_group', 'units', 'hospital', 'needed_by', 'urgency', 'contact_name', 'contact_phone', 'note'])),
            ['user_id' => $u->id, 'family_id' => $a['family_id'], 'country' => $a['country'], 'pradesh' => $a['pradesh'], 'district' => $d['district'] ?? $a['district'], 'city' => $d['city'] ?? $a['city'], 'zone' => $a['zone'],
                'status' => 'open', 'created_at' => now(), 'updated_at' => now()]));
        $req = DB::table('blood_requests')->find($id);
        $emergency = ($d['urgency'] ?? 'normal') === 'emergency';
        $donors = $this->candidates($req, $emergency);
        foreach ($donors as $dn) {
            DB::table('blood_links')->insert(['donor_user_id' => $dn->user_id, 'requester_user_id' => $u->id, 'request_id' => $id, 'message' => $d['note'] ?? null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->tell($dn->user_id, ($emergency ? 'EMERGENCY: ' : '') . "{$req->blood_group} blood needed", "{$req->hospital}, {$req->city}. If you can donate, please approve in the app.", ['type' => 'blood_request', 'id' => $id]);
        }
        DB::table('blood_requests')->where('id', $id)->update(['notified' => $donors->count()]);
        if ($emergency) UserNotification::notifyAdmins("Emergency blood request: {$req->blood_group} - {$req->hospital}, {$req->city}", null, $req->pradesh);
        return [$id, $donors->count()];
    }

    public function candidates(object $req, bool $widen)
    {
        $groups = self::COMPATIBLE[$req->blood_group] ?? [$req->blood_group];
        $base = fn () => DB::table('blood_donors')->where('status', 'active')->where('available', true)->where('user_id', '!=', $req->user_id)->whereIn('blood_group', $groups)
            ->when($req->urgency === 'emergency', fn ($q) => $q->where('emergency_ok', true));
        $pick = fn ($q) => $q->limit(200)->get()->filter(fn ($d) => $this->isEligible($d))->take(50)->values();
        $found = $pick($base()->where('city', $req->city));
        if ($found->isEmpty() && $widen && $req->district) $found = $pick($base()->where('district', $req->district));
        if ($found->isEmpty() && $widen && $req->pradesh) $found = $pick($base()->where('pradesh', $req->pradesh));
        return $found;
    }

    /** Daily: tells donors who became eligible again today. */
    public function remindEligible(): int
    {
        $n = 0;
        foreach (DB::table('blood_donors')->where('status', 'active')->where('available', true)->whereDate('last_donated_on', today()->subDays($this->gapDays())->toDateString())->get() as $d) {
            $this->tell($d->user_id, 'You can donate blood again', 'It has been ' . $this->gapDays() . ' days since your last donation. Be ready to help someone in need.'); $n++;
        }
        return $n;
    }

    /* ------------------------------------------------------------------ blood donation drives (camps) */

    public function driveLive(int $id, $by = null): void
    {
        $dr = DB::table('blood_drives')->find($id); if (! $dr) return;
        DB::table('blood_drives')->where('id', $id)->update(['status' => 'live', 'updated_at' => now()]);
        foreach (DB::table('blood_donors')->where('status', 'active')->when($dr->city, fn ($q, $c) => $q->where('city', $c))->limit(500)->pluck('user_id') as $uid)
            $this->tell($uid, 'Blood donation camp', "{$dr->title} - {$dr->venue}, " . \Carbon\Carbon::parse($dr->drive_date)->format('d M') . '. Please register.', ['type' => 'blood_drive', 'id' => $id]);
        $this->tell($dr->user_id, 'Your blood camp is live', $dr->title);
    }

    public function driveRegister(User $u, object $dr): int
    {
        abort_unless($dr->status === 'live', 422, 'This camp is not open yet.');
        abort_if(\Carbon\Carbon::parse($dr->drive_date)->endOfDay()->isPast(), 422, 'This camp has already taken place.');
        abort_if(DB::table('blood_drive_regs')->where(['drive_id' => $dr->id, 'user_id' => $u->id])->exists(), 422, 'You are already registered.');
        return DB::table('blood_drive_regs')->insertGetId(['drive_id' => $dr->id, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Admin closes a camp: units collected, donors who gave blood get their history updated. */
    public function driveComplete(int $id, $by = null): void
    {
        $dr = DB::table('blood_drives')->find($id); if (! $dr || $dr->status === 'done') return;
        DB::table('blood_drives')->where('id', $id)->update(['status' => 'done', 'updated_at' => now()]);
        foreach (DB::table('blood_drive_regs')->where(['drive_id' => $id, 'donated' => true])->pluck('user_id') as $uid) { $this->recordDonation($uid, $dr->drive_date); $this->tell($uid, 'Thank you!', "Thank you for your donation at {$dr->title}."); }
    }
}
