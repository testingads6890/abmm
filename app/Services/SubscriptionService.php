<?php

namespace App\Services;

use App\Models\{Plan, Subscription, UserNotification};
use App\Services\Payments\PaymentLedger;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private PaymentLedger $ledger) {}

    /** Creates a pending subscription + its payment. The subscription turns active when the payment is confirmed. */
    public function start(Plan $plan, array $who, ?string $coupon = null, ?string $gateway = null): array
    {
        return DB::transaction(function () use ($plan, $who, $coupon, $gateway) {
            $s = Subscription::create([
                'user_id' => $who['user_id'] ?? null, 'family_id' => $who['family_id'] ?? null,
                'subscriber_type' => $plan->audience, 'subscriber_id' => $who['subscriber_id'] ?? null, 'plan_id' => $plan->id, 'status' => 'pending',
            ]);
            $p = $this->ledger->create([
                'purpose' => 'subscription', 'purpose_ref' => (string) $s->id, 'amount' => $plan->price, 'currency' => $plan->currency, 'audience' => $plan->audience,
                'country' => $who['country'] ?? null, 'city' => $who['city'] ?? null, 'user_id' => $who['user_id'] ?? null, 'family_id' => $who['family_id'] ?? null,
                'payer_name' => $who['name'] ?? null, 'payer_email' => $who['email'] ?? null, 'payer_mobile' => $who['mobile'] ?? null,
                'coupon' => $coupon, 'gateway' => $gateway, 'meta' => ['title' => $plan->name, 'plan' => $plan->code],
            ]);
            return [$s, $p];
        });
    }

    public function activate(int $id): void
    {
        $s = Subscription::with('plan')->find($id);
        if (! $s || $s->status === 'active') return;
        // Renewal of the same plan stacks on top of the remaining time.
        $prev = Subscription::where('status', 'active')->where('id', '!=', $s->id)->where('plan_id', $s->plan_id)
            ->where('user_id', $s->user_id)->where('family_id', $s->family_id)->where('subscriber_id', $s->subscriber_id)->orderByDesc('ends_on')->first();
        $start = ($prev && $prev->ends_on && $prev->ends_on->gte(today())) ? $prev->ends_on->copy()->addDay() : today();
        $months = $s->plan->months();
        $s->update(['status' => 'active', 'starts_on' => $start, 'ends_on' => $months ? $start->copy()->addMonthsNoOverflow($months)->subDay() : null]);
        if ($prev) $prev->update(['status' => 'expired']);
        $this->applyListing($s->fresh());
    }

    /** A listing plan may only be bought for a page the buyer owns (business of the family, vendor / institution of the user). */
    public function ownsSubscriber(string $audience, ?int $id, int $userId, ?int $familyId): bool
    {
        return match ($audience) {
            'business' => $id && DB::table('businesses')->where('id', $id)->where('family_id', $familyId)->exists(),
            'vendor' => $id && DB::table('event_vendors')->where('id', $id)->where('user_id', $userId)->exists(),
            'institution' => $id && DB::table('directory_entries')->where('id', $id)->where('owner_user_id', $userId)->exists(),
            'organiser', 'recruiter' => $id && DB::table('partners')->where(['id' => $id, 'user_id' => $userId, 'kind' => $audience])->exists(),
            'bureau' => $id === null || DB::table('partners')->where(['id' => $id, 'user_id' => $userId, 'kind' => 'bureau'])->exists(),
            default => true,
        };
    }

    /** Paid listings: the business / vendor / institution page stays visible until the subscription ends. */
    private function applyListing(Subscription $s): void
    {
        if (! $s->subscriber_id) return;
        if (in_array($s->subscriber_type, ['bureau', 'organiser', 'recruiter'], true)) { app(PartnerService::class)->extend((int) $s->subscriber_id, $s->ends_on?->toDateString()); return; }
        $table = ['business' => 'businesses', 'vendor' => 'event_vendors', 'institution' => 'directory_entries'][$s->subscriber_type] ?? null;
        if (! $table) return;
        $upd = ['listing_until' => $s->ends_on?->toDateString(), 'updated_at' => now()];
        if ($s->subscriber_type === 'vendor') $upd['top_until'] = collect((array) $s->plan->features)->contains(fn ($f) => stripos((string) $f, 'top') !== false) ? $s->ends_on?->toDateString() : null;
        DB::table($table)->where('id', $s->subscriber_id)->update($upd);
    }

    public function cancel(int $id): void { Subscription::whereKey($id)->update(['status' => 'cancelled', 'auto_renew' => false]); }

    public function expireDue(): int { return Subscription::where('status', 'active')->whereNotNull('ends_on')->where('ends_on', '<', today())->update(['status' => 'expired']); }

    /** Reminds subscribers whose plan ends within $days (once). */
    public function remindDue(int $days = 7): int
    {
        $n = 0;
        Subscription::with('plan')->where('status', 'active')->whereNull('reminded_at')->whereNotNull('ends_on')
            ->whereBetween('ends_on', [today(), today()->addDays($days)])->get()->each(function (Subscription $s) use (&$n) {
                if ($s->user_id) UserNotification::create(['user_id' => $s->user_id, 'title' => "Your plan \"{$s->plan->name}\" ends on {$s->ends_on->format('d M Y')}. Please renew."]);
                $s->update(['reminded_at' => now()]); $n++;
            });
        return $n;
    }

    /** Does this person / business have a live subscription for the audience? Used to gate paid features. */
    public function hasLive(string $audience, ?int $userId = null, ?int $familyId = null, ?int $subscriberId = null): bool
    {
        return Subscription::where('subscriber_type', $audience)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->when($familyId, fn ($q) => $q->where('family_id', $familyId))
            ->when($subscriberId, fn ($q) => $q->where('subscriber_id', $subscriberId))->exists();
    }
}
