<?php

namespace App\Services;

use App\Models\{Family, JobPost, Setting, User, UserNotification};
use App\Services\Payments\PaymentLedger;
use App\Support\Area;
use Illuminate\Support\Facades\DB;

/**
 * Milestone E - careers: paid / featured job posts, job alerts, campus & internship drives, mentor <-> mentee matching, sessions, career counselling.
 */
class CareerService
{
    public function __construct(private PaymentLedger $ledger) {}

    private function tell(?int $uid, string $title, string $body, array $data = []): void
    {
        if (! $uid || ! ($u = User::withoutGlobalScopes()->find($uid))) return;
        try { app(GreetingService::class)->notify($u, $title, $body, $data); } catch (\Throwable $e) { report($e); UserNotification::create(['user_id' => $uid, 'title' => "$title: $body"]); }
    }

    /* ------------------------------------------------------------------ paid and featured job posts */

    /** Admin switch (Settings key jobs_paid_posting = 1): every new job needs a paid "post" pack before it goes for approval. */
    public function paidPosting(): bool { return Setting::get('jobs_paid_posting', '0') === '1'; }

    public function packs(?string $kind = null)
    {
        return DB::table('job_packs')->where('is_active', true)->when($kind, fn ($q, $k) => $q->where('kind', $k))->orderBy('sort')->orderBy('id')->get();
    }

    /** @return array{0:int,1:\App\Models\Payment} order id + payment */
    public function order(User $u, JobPost $j, object $pack, ?string $coupon = null): array
    {
        $a = Area::of($u); $f = $a['family_id'] ? Family::withoutGlobalScopes()->find($a['family_id']) : null;
        $oid = DB::table('job_orders')->insertGetId(['job_id' => $j->id, 'pack_id' => $pack->id, 'user_id' => $u->id, 'amount' => $pack->price, 'status' => 'awaiting_payment', 'created_at' => now(), 'updated_at' => now()]);
        $p = $this->ledger->create(['purpose' => 'jobpack', 'purpose_ref' => (string) $oid, 'amount' => $pack->price, 'currency' => $pack->currency, 'country' => $a['country'], 'city' => $a['city'], 'user_id' => $u->id, 'family_id' => $a['family_id'],
            'payer_name' => $f?->head_name ?: $u->name, 'payer_email' => $f?->email, 'payer_mobile' => $f ? PhoneService::e164($f->mobile, $f->country_code ?: '+91') : null, 'coupon' => $coupon, 'audience' => 'member', 'meta' => ['title' => $pack->name . ': ' . $j->title]]);
        DB::table('job_orders')->where('id', $oid)->update(['payment_id' => $p->id]);
        return [$oid, $p];
    }

    /** Settle hook: payment confirmed. */
    public function paid(int $orderId): void
    {
        $o = DB::table('job_orders')->find($orderId); if (! $o || $o->status === 'paid') return;
        $pack = DB::table('job_packs')->find($o->pack_id); $j = JobPost::withoutGlobalScopes()->find($o->job_id);
        DB::table('job_orders')->where('id', $orderId)->update(['status' => 'paid', 'updated_at' => now()]);
        if (! $pack || ! $j) return;
        $days = max(1, (int) $pack->days);
        if ($pack->kind === 'featured') {
            $from = $j->featured_until && $j->featured_until->isFuture() ? $j->featured_until : now();
            $j->update(['featured_until' => $from->copy()->addDays($days), 'paid_order_id' => $orderId]);
            $this->tell($o->user_id, 'Featured job', "{$j->title} will be shown at the top for {$days} days.");
        } else {
            if ($j->status === 'unpaid') {
                $j->update(['status' => 'pending', 'paid_order_id' => $orderId]);
                UserNotification::notifyAdmins('New (paid) job for approval: ' . $j->title, route('admin.jobs.show', $j->id), $j->pradesh);
                $this->tell($o->user_id, 'Payment mila', 'Your job post is now under review.');
            } else {                                                        // renewal of a live / expired post
                $from = $j->expires_at && $j->expires_at->isFuture() ? $j->expires_at : now();
                $j->update(['expires_at' => $from->copy()->addDays($days), 'status' => $j->status === 'closed' ? 'closed' : 'live', 'paid_order_id' => $orderId]);
                $this->tell($o->user_id, 'Job post renew', "{$j->title} has been extended by {$days} days.");
            }
        }
    }

    public function refunded(int $orderId): void
    {
        $o = DB::table('job_orders')->find($orderId); if (! $o) return;
        DB::table('job_orders')->where('id', $orderId)->update(['status' => 'cancelled', 'updated_at' => now()]);
        $j = JobPost::withoutGlobalScopes()->find($o->job_id); $pack = DB::table('job_packs')->find($o->pack_id);
        if (! $j || ! $pack) return;
        if ($pack->kind === 'featured') $j->update(['featured_until' => null]);
        elseif ($j->status === 'unpaid') $j->update(['status' => 'closed']);
    }

    /* ------------------------------------------------------------------ job alerts */

    public function alertMatches(JobPost $j, object $a): bool
    {
        if ($a->category && $a->category !== $j->category) return false;
        if ($a->job_type && $a->job_type !== $j->job_type) return false;
        if ($a->work_mode && $a->work_mode !== $j->work_mode) return false;
        if ($a->city && stripos((string) $j->city, $a->city) !== 0) return false;
        if ($a->min_salary && $j->salary_max !== null && $j->salary_max < $a->min_salary) return false;
        if ($a->min_salary && $j->salary_max === null && $j->salary_min === null) return false;
        if (filled($a->keywords)) {
            $hay = mb_strtolower($j->title . ' ' . $j->company . ' ' . $j->skills . ' ' . $j->category);
            $hit = false;
            foreach (preg_split('/[,;]+/', (string) $a->keywords) as $k) if (($k = trim(mb_strtolower($k))) !== '' && str_contains($hay, $k)) { $hit = true; break; }
            if (! $hit) return false;
        }
        return true;
    }

    /** Called when an admin approves a job: pushes it to every matching instant alert. */
    public function dispatchInstant(JobPost $j): int
    {
        $n = 0;
        foreach (DB::table('job_alerts')->where(['active' => true, 'frequency' => 'instant'])->where('user_id', '!=', $j->user_id)->get() as $a) {
            if (! $this->alertMatches($j, $a)) continue;
            $this->tell($a->user_id, 'Job alert', $j->title . ($j->company ? " - {$j->company}" : '') . ($j->city ? " ({$j->city})" : ''), ['type' => 'job', 'id' => $j->id]);
            DB::table('job_alerts')->where('id', $a->id)->update(['last_sent_at' => now()]); $n++;
        }
        return $n;
    }

    /** Daily digest for frequency=daily alerts (scheduled). */
    public function digest(): int
    {
        $n = 0;
        foreach (DB::table('job_alerts')->where(['active' => true, 'frequency' => 'daily'])->where(fn ($q) => $q->whereNull('last_sent_at')->orWhere('last_sent_at', '<=', now()->subHours(23)))->get() as $a) {
            $since = $a->last_sent_at ? \Carbon\Carbon::parse($a->last_sent_at) : now()->subDay();
            $jobs = JobPost::withoutGlobalScopes()->live()->where('created_at', '>', $since)->where('user_id', '!=', $a->user_id)->latest()->limit(100)->get()->filter(fn ($j) => $this->alertMatches($j, $a));
            DB::table('job_alerts')->where('id', $a->id)->update(['last_sent_at' => now()]);
            if ($jobs->isEmpty()) continue;
            $this->tell($a->user_id, 'Today\'s new jobs', $jobs->count() . ' new jobs matched your alert: ' . $jobs->take(3)->pluck('title')->implode(', '));
            $n++;
        }
        return $n;
    }

    /* ------------------------------------------------------------------ campus / internship drives */

    public function driveRegister(User $u, object $drive, array $d): int
    {
        abort_unless($drive->status === 'live', 422, 'This drive is not open yet.');
        abort_if($drive->last_date && \Carbon\Carbon::parse($drive->last_date)->endOfDay()->isPast(), 422, 'The registration last date has passed.');
        abort_if(DB::table('drive_registrations')->where(['drive_id' => $drive->id, 'user_id' => $u->id])->exists(), 422, 'You are already registered.');
        $id = DB::table('drive_registrations')->insertGetId($d + ['drive_id' => $drive->id, 'user_id' => $u->id, 'status' => 'registered', 'created_at' => now(), 'updated_at' => now()]);
        $this->tell($drive->user_id, 'Drive registration', "{$u->name} registered for '{$drive->title}'.");
        return $id;
    }

    /* ------------------------------------------------------------------ mentors */

    public function mentorCapacity(object $m): int
    {
        return max(0, $m->max_mentees - DB::table('mentorships')->where('mentor_id', $m->id)->where('status', 'active')->count());
    }

    /** Best mentors for a mentee: skill / interest overlap + same city + mode fit. */
    public function matchMentors(User $u, ?string $topic = null, bool $counselling = false, int $limit = 20): array
    {
        $a = Area::of($u);
        $mp = DB::table('mentee_profiles')->where('user_id', $u->id)->first();
        $jp = DB::table('job_profiles')->where('user_id', $u->id)->first();
        $want = Area::tokens(trim(($topic ?? '') . ' ' . ($mp->interests ?? '') . ' ' . ($mp->goals ?? '') . ' ' . ($jp->skills ?? '') . ' ' . ($jp->category ?? '') . ' ' . ($jp->headline ?? '')));
        $out = [];
        foreach (DB::table('mentor_profiles')->where('status', 'approved')->where('user_id', '!=', $u->id)->when($counselling, fn ($q) => $q->where('offers_counselling', true))->get() as $m) {
            if ($this->mentorCapacity($m) < 1) continue;
            $have = Area::tokens($m->expertise . ' ' . $m->industry . ' ' . $m->headline);
            $common = array_values(array_intersect($want, $have));
            $score = min(70, count($common) * 18);
            $reasons = $common ? ['Skills match: ' . implode(', ', array_slice($common, 0, 4))] : [];
            if ($a['city'] && strcasecmp((string) $m->city, (string) $a['city']) === 0) { $score += 15; $reasons[] = 'In your city'; }
            elseif ($a['pradesh'] && $m->pradesh === $a['pradesh']) { $score += 6; $reasons[] = 'In your state'; }
            if ($mp && ($m->mode === 'both' || $m->mode === $mp->mode)) $score += 5;
            if ($m->verified) { $score += 5; $reasons[] = 'Verified'; }
            $score += min(5, intdiv((int) $m->experience_years, 4));
            if ($want && ! $common) $score = min($score, 30);
            $out[] = ['mentor' => $m, 'score' => min(100, $score), 'reasons' => $reasons];
        }
        usort($out, fn ($x, $y) => $y['score'] <=> $x['score']);
        return array_slice($out, 0, $limit);
    }

    public function requestMentor(User $u, object $m, array $d): int
    {
        abort_unless($m->status === 'approved', 422, 'This mentor is not available right now.');
        abort_if($m->user_id === $u->id, 422, 'You cannot send a request to yourself.');
        $kind = $d['kind'] ?? 'mentoring';
        abort_if($kind === 'counselling' && ! $m->offers_counselling, 422, 'This mentor does not offer career counselling.');
        abort_if(DB::table('mentorships')->where(['mentor_id' => $m->id, 'mentee_user_id' => $u->id])->whereIn('status', ['requested', 'active'])->exists(), 422, 'Your request is already in progress.');
        abort_if($this->mentorCapacity($m) < 1, 422, 'All of the mentor\'s slots are full.');
        $id = DB::table('mentorships')->insertGetId(['mentor_id' => $m->id, 'mentee_user_id' => $u->id, 'mentor_user_id' => $m->user_id, 'kind' => $kind, 'topic' => $d['topic'], 'message' => $d['message'] ?? null, 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);
        $this->tell($m->user_id, $kind === 'counselling' ? 'New counselling request' : 'New mentoring request', "{$u->name}: {$d['topic']}");
        return $id;
    }

    public function respond(object $ms, bool $accept, ?string $note): void
    {
        abort_unless($ms->status === 'requested', 422, 'No further action is possible on this request.');
        if ($accept) abort_if($this->mentorCapacity(DB::table('mentor_profiles')->find($ms->mentor_id)) < 1, 422, 'All your mentee slots are full.');
        DB::table('mentorships')->where('id', $ms->id)->update(['status' => $accept ? 'active' : 'declined', 'mentor_note' => $note, 'updated_at' => now()]);
        $this->tell($ms->mentee_user_id, $accept ? 'Mentor accepted the request' : 'Mentor request declined', $ms->topic . ($note ? " - $note" : ''));
    }

    public function schedule(object $ms, User $by, array $d): int
    {
        abort_unless($ms->status === 'active', 422, 'A session can only be held in an active mentorship.');
        $id = DB::table('mentor_sessions')->insertGetId(['mentorship_id' => $ms->id, 'scheduled_at' => $d['scheduled_at'], 'duration_min' => $d['duration_min'] ?? 30, 'mode' => $d['mode'] ?? 'online',
            'meeting_link' => $d['meeting_link'] ?? null, 'agenda' => $d['agenda'] ?? null, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()]);
        $other = $by->id === $ms->mentor_user_id ? $ms->mentee_user_id : $ms->mentor_user_id;
        $this->tell($other, 'Session scheduled', \Carbon\Carbon::parse($d['scheduled_at'])->format('d M, h:i A') . ' - ' . $ms->topic);
        return $id;
    }

    /** Hourly: reminds both sides of sessions starting in the next 24 hours. */
    public function remindSessions(): int
    {
        $n = 0;
        foreach (DB::table('mentor_sessions as s')->join('mentorships as m', 'm.id', '=', 's.mentorship_id')->where('s.status', 'scheduled')->where('s.reminded', false)
            ->whereBetween('s.scheduled_at', [now(), now()->addDay()])->get(['s.*', 'm.mentor_user_id', 'm.mentee_user_id', 'm.topic']) as $s) {
            foreach ([$s->mentor_user_id, $s->mentee_user_id] as $uid) $this->tell($uid, 'Session reminder', \Carbon\Carbon::parse($s->scheduled_at)->format('d M, h:i A') . ' - ' . $s->topic . ($s->meeting_link ? ' | ' . $s->meeting_link : ''));
            DB::table('mentor_sessions')->where('id', $s->id)->update(['reminded' => true]); $n++;
        }
        return $n;
    }
}
