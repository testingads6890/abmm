<?php

namespace App\Services;

use App\Models\{Plan, Subscription, User, UserNotification};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * Partner dashboards: Marriage Bureau, Event Organiser, Job Recruiter.
 * A partner (a member of the community running a bureau / event company / hiring firm) registers, gets approved, and then
 * pays a subscription (audience = kind). Without a LIVE subscription the dashboard is locked: records cannot be added, listings
 * are hidden from members and lead / applicant details are masked. The plan's `max_items` limits records per subscription period.
 */
class PartnerService
{
    public const KINDS = [
        'bureau' => ['label' => 'Marriage Bureau', 'table' => 'matrimony_profiles', 'noun' => 'profile', 'subject' => 'profile'],
        'organiser' => ['label' => 'Event Organiser', 'table' => 'events', 'noun' => 'event', 'subject' => 'event'],
        'recruiter' => ['label' => 'Job Recruiter', 'table' => 'jobs_board', 'noun' => 'job', 'subject' => 'job'],
    ];

    public function liveSub(object $partner): ?Subscription
    {
        return Subscription::with('plan')->where(['subscriber_type' => $partner->kind, 'subscriber_id' => $partner->id, 'status' => 'active'])
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()))->orderByDesc('ends_on')->first();
    }

    public function plans(string $kind, ?string $country = null)
    {
        return Plan::active()->where('audience', $kind)->when($country, fn ($q, $c) => $q->where(fn ($w) => $w->whereNull('country')->orWhere('country', $c)))
            ->orderBy('sort')->orderBy('price')->get(['id', 'code', 'name', 'period', 'currency', 'price', 'features', 'max_items']);
    }

    /** records created in the current subscription period vs the plan limit */
    public function quota(object $partner, ?Subscription $sub = null): array
    {
        $sub ??= $this->liveSub($partner);
        $tbl = self::KINDS[$partner->kind]['table'];
        $used = $sub ? DB::table($tbl)->where('partner_id', $partner->id)->where('created_at', '>=', $sub->starts_on?->toDateString() ?? $sub->created_at)->count() : 0;
        $limit = $sub?->plan?->max_items;
        return ['limit' => $limit, 'used' => $used, 'left' => $limit === null ? null : max(0, $limit - $used)];
    }

    /** Throws a 402 JSON response (with the plans to buy) when the partner cannot add records. Returns the live subscription. */
    public function gate(object $partner, bool $forCreate = true): Subscription
    {
        $deny = fn (string $msg, array $extra = []) => throw new HttpResponseException(response()->json(['message' => $msg, 'locked' => true] + $extra, 402));
        if ($partner->status !== 'active') $deny('Your ' . self::KINDS[$partner->kind]['label'] . ' profile is not approved yet.');
        $sub = $this->liveSub($partner);
        if (! $sub) $deny('A subscription is required for this feature. Please choose a plan.', ['plans' => $this->plans($partner->kind)]);
        if ($forCreate) {
            $q = $this->quota($partner, $sub);
            if ($q['left'] !== null && $q['left'] <= 0) $deny('Your plan limit (' . $q['limit'] . ') is reached. Please upgrade / renew.', ['quota' => $q, 'plans' => $this->plans($partner->kind)]);
        }
        return $sub;
    }

    /** A subscription started / renewed: the partner's records go live until the new end date. */
    public function extend(int $partnerId, ?string $endsOn): void
    {
        $p = DB::table('partners')->find($partnerId);
        if (! $p) return;
        if ($p->kind === 'bureau') DB::table('matrimony_profiles')->where('partner_id', $partnerId)->update(['status' => 'paid', 'visible' => true, 'paid_until' => $endsOn, 'updated_at' => now()]);
        if ($p->kind === 'organiser') DB::table('events')->where('partner_id', $partnerId)->update(['listing_until' => $endsOn, 'updated_at' => now()]);
        if ($p->kind === 'recruiter') DB::table('jobs_board')->where('partner_id', $partnerId)->where('status', 'live')->update(['expires_at' => $endsOn ? $endsOn . ' 23:59:59' : null, 'updated_at' => now()]);
        UserNotification::create(['user_id' => $p->user_id, 'title' => 'Your subscription is now active' . ($endsOn ? " (until $endsOn)" : '') . '. Dashboard khul gaya hai.']);
    }

    /** a member contacts a partner (or expresses interest in one of its records) */
    public function enquire(int $partnerId, User $from, ?string $type, ?int $subjectId, ?string $message, ?string $phone = null): int
    {
        $p = DB::table('partners')->where('status', 'active')->find($partnerId);
        abort_unless($p, 404);
        $id = DB::table('partner_enquiries')->insertGetId(['partner_id' => $partnerId, 'from_user_id' => $from->id, 'subject_type' => $type, 'subject_id' => $subjectId,
            'name' => $from->name, 'phone' => $phone ?: $from->mobile, 'message' => $message, 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
        UserNotification::create(['user_id' => $p->user_id, 'title' => 'New enquiry: ' . $from->name . ($message ? ' - ' . mb_substr($message, 0, 60) : '')]);
        return $id;
    }

    public function stats(object $partner): array
    {
        $enq = DB::table('partner_enquiries')->where('partner_id', $partner->id);
        $base = ['enquiries' => (clone $enq)->count(), 'enquiries_new' => (clone $enq)->where('status', 'new')->count()];
        if ($partner->kind === 'bureau') {
            $q = DB::table('matrimony_profiles')->where('partner_id', $partner->id);
            return $base + ['profiles' => (clone $q)->count(), 'profiles_live' => (clone $q)->where('status', 'paid')->where('visible', true)->where(fn ($w) => $w->whereNull('paid_until')->orWhere('paid_until', '>=', today()))->count(),
                'male' => (clone $q)->where('gender', 'Male')->count(), 'female' => (clone $q)->where('gender', 'Female')->count(),
                'interests_received' => DB::table('matrimony_interests')->whereIn('to_profile_id', (clone $q)->pluck('id'))->count(),
                'interests_accepted' => DB::table('matrimony_interests')->whereIn('to_profile_id', (clone $q)->pluck('id'))->where('status', 'accepted')->count()];
        }
        if ($partner->kind === 'recruiter') {
            $q = DB::table('jobs_board')->where('partner_id', $partner->id); $ids = (clone $q)->pluck('id'); $a = DB::table('job_applications')->whereIn('job_id', $ids);
            return $base + ['jobs' => (clone $q)->count(), 'jobs_live' => (clone $q)->where('status', 'live')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(), 'views' => (int) (clone $q)->sum('views'),
                'applications' => (clone $a)->count(), 'shortlisted' => (clone $a)->where('status', 'shortlisted')->count(), 'hired' => (clone $a)->where('status', 'hired')->count(), 'vacancies' => (int) (clone $q)->sum('vacancies')];
        }
        $q = DB::table('events')->where('partner_id', $partner->id); $ids = (clone $q)->pluck('id');
        return $base + ['events' => (clone $q)->count(), 'upcoming' => (clone $q)->whereDate('event_date', '>=', today())->count(),
            'rsvps' => (int) DB::table('event_rsvps')->whereIn('event_id', $ids)->where('status', 'going')->selectRaw('COALESCE(SUM(1 + guests),0) s')->value('s'),
            'tickets_sold' => (int) DB::table('ticket_orders')->whereIn('event_id', $ids)->where('status', 'paid')->sum('qty'),
            'ticket_revenue' => (float) DB::table('ticket_orders')->whereIn('event_id', $ids)->where('status', 'paid')->sum('amount')];
    }

    public function dashboard(object $partner): array
    {
        $sub = $this->liveSub($partner);
        $locked = ! $sub;
        $tbl = self::KINDS[$partner->kind]['table'];
        $recent = DB::table($tbl)->where('partner_id', $partner->id)->orderByDesc('id')->limit(20)->get();
        $enq = DB::table('partner_enquiries')->where('partner_id', $partner->id)->orderByDesc('id')->limit(20)->get()
            ->map(fn ($e) => $locked ? ['id' => $e->id, 'status' => $e->status, 'at' => $e->created_at, 'locked' => true] : (array) $e);   // lead details need a live subscription
        return ['partner' => $partner, 'kind_label' => self::KINDS[$partner->kind]['label'], 'locked' => $locked,
            'subscription' => $sub ? ['plan' => $sub->plan->name, 'starts_on' => $sub->starts_on?->toDateString(), 'ends_on' => $sub->ends_on?->toDateString(), 'days_left' => $sub->ends_on ? today()->diffInDays($sub->ends_on, false) : null] : null,
            'quota' => $this->quota($partner, $sub), 'stats' => $this->stats($partner), 'recent' => $recent, 'enquiries' => $enq,
            'plans' => $locked || ($sub->ends_on && today()->diffInDays($sub->ends_on, false) <= 15) ? $this->plans($partner->kind) : []];
    }
}
