<?php

namespace App\Services\Payments;

use App\Models\{Coupon, CountrySetting, FormSubmission, Payment, PaymentRefund};
use App\Services\{ReceiptService, SubscriptionService};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Unified payments ledger. EVERY online payment (subscription, form fee, event, ad ...) is created, confirmed, refunded and reconciled here,
 * so receipts, revenue reports and refunds work the same for every module and every country.
 */
class PaymentLedger
{
    public function gateway(string $name): Gateway
    {
        return match ($name) { 'stripe' => new StripeGateway, 'razorpay' => new RazorpayGateway, default => new ManualGateway };
    }

    /**
     * @param array $a purpose, purpose_ref, amount (gross, tax inclusive), country, city, user_id, family_id, payer_name/email/mobile, coupon, gateway, currency, meta
     */
    public function create(array $a): Payment
    {
        $cs = CountrySetting::forCountry($a['country'] ?? null);
        $amount = round((float) $a['amount'], 2);
        $discount = 0.0; $coupon = null;
        if (! empty($a['coupon'])) {
            $c = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($a['coupon']))])->first();
            $d = $c?->discountFor($amount, $a['audience'] ?? null);
            if ($d === null) throw ValidationException::withMessages(['coupon' => 'The coupon is not valid or has expired.']);
            $discount = $d; $coupon = $c->code;
        }
        $payable = max(0, round($amount - $discount, 2));
        $rate = in_array($a['purpose'], ['campaign'], true) ? 0.0 : (float) $cs->tax_rate;      // donations carry no GST / VAT
        $tax = $rate > 0 ? round($payable - $payable / (1 + $rate / 100), 2) : 0.0;     // prices are tax-inclusive
        $gw = $a['gateway'] ?? $cs->gateway;
        if ($payable <= 0) $gw = 'manual';

        return DB::transaction(function () use ($a, $cs, $payable, $discount, $coupon, $tax, $gw) {
            $p = Payment::create([
                'number' => 'TMP' . uniqid(), 'purpose' => $a['purpose'], 'purpose_ref' => $a['purpose_ref'] ?? null,
                'user_id' => $a['user_id'] ?? null, 'family_id' => $a['family_id'] ?? null, 'country' => $a['country'] ?? $cs->country, 'city' => $a['city'] ?? null,
                'payer_name' => $a['payer_name'] ?? null, 'payer_email' => $a['payer_email'] ?? null, 'payer_mobile' => $a['payer_mobile'] ?? null,
                'currency' => $a['currency'] ?? $cs->currency, 'amount' => $payable, 'tax_amount' => $tax, 'discount' => $discount, 'coupon' => $coupon,
                'gateway' => $gw, 'status' => 'created', 'meta' => $a['meta'] ?? null,
            ]);
            $p->update(['number' => sprintf('PAY-%s-%06d', now()->format('Y'), $p->id)]);
            return $p;
        });
    }

    /** Starts the gateway checkout. Free (0) payments are settled immediately. */
    public function checkout(Payment $p, array $opts = []): array
    {
        if ($p->amount <= 0) { $this->markPaid($p, 'FREE-' . $p->number); return ['paid' => true, 'payment' => $p->fresh()]; }
        $gw = $this->gateway($p->gateway);
        abort_unless($gw->configured(), 422, "Payment gateway ({$p->gateway}) is not configured yet.");
        $o = $gw->createOrder($p, $opts);
        $p->update(['gateway_order_id' => $o['order_id']]);
        return $o + ['payment_no' => $p->number, 'amount' => $p->amount, 'currency' => $p->currency];
    }

    /** Idempotent: returns true only the first time the payment becomes paid. */
    public function markPaid(Payment $p, ?string $gatewayPaymentId = null, bool $reconciled = false): bool
    {
        $first = DB::transaction(function () use ($p, $gatewayPaymentId, $reconciled) {
            $row = Payment::whereKey($p->id)->lockForUpdate()->first();
            if (! $row || in_array($row->status, ['paid', 'refunded', 'partial_refund'], true)) return false;
            $row->update(['status' => 'paid', 'paid_at' => now(), 'gateway_payment_id' => $gatewayPaymentId ?: $row->gateway_payment_id, 'reconciled' => $reconciled]);
            if ($row->coupon) Coupon::where('code', $row->coupon)->increment('used');
            $this->settle($row);
            return true;
        });
        $p->refresh();
        if ($first) { try { app(ReceiptService::class)->issueAndDeliver($p); } catch (\Throwable $e) { report($e); } }
        return $first;
    }

    private function settle(Payment $p): void
    {
        if ($p->purpose === 'subscription' && $p->purpose_ref) app(SubscriptionService::class)->activate((int) $p->purpose_ref);
        if ($p->purpose === 'form' && $p->purpose_ref) FormSubmission::whereKey($p->purpose_ref)->update(['payment_status' => 'paid', 'payment_id' => $p->id]);
        if ($p->purpose === 'ticket' && $p->purpose_ref) app(\App\Services\TicketService::class)->confirm((int) $p->purpose_ref);
        if ($p->purpose === 'ad' && $p->purpose_ref) app(\App\Services\AdService::class)->paid((int) $p->purpose_ref);
        if ($p->purpose === 'jobpack' && $p->purpose_ref) app(\App\Services\CareerService::class)->paid((int) $p->purpose_ref);
        if ($p->purpose === 'campaign' && $p->purpose_ref) app(\App\Services\GivingService::class)->paid((int) $p->purpose_ref);
        if ($p->purpose === 'course' && $p->purpose_ref) app(\App\Services\AcademyService::class)->paid((int) $p->purpose_ref);
        if ($p->purpose === 'housie' && $p->purpose_ref) app(\App\Services\HousieService::class)->paid((int) $p->purpose_ref);
    }

    public function markFailed(Payment $p): void { if ($p->status === 'created') $p->update(['status' => 'failed']); }

    public function refund(Payment $p, float $amount, string $reason, ?int $byUser = null): PaymentRefund
    {
        return DB::transaction(function () use ($p, $amount, $reason, $byUser) {
            $row = Payment::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if (! in_array($row->status, ['paid', 'partial_refund'], true)) throw ValidationException::withMessages(['amount' => 'Only a paid payment can be refunded.']);
            $left = round($row->amount - $row->refunded, 2);
            $amount = round($amount, 2);
            if ($amount <= 0 || $amount > $left) throw ValidationException::withMessages(['amount' => "Refund must be greater than 0 and ≤ {$left}."]);
            $rid = $this->gateway($row->gateway)->refund($row, $amount, $reason);
            $r = PaymentRefund::create(['payment_id' => $row->id, 'amount' => $amount, 'reason' => $reason, 'gateway_refund_id' => $rid, 'status' => 'done', 'by_user' => $byUser]);
            $total = round($row->refunded + $amount, 2);
            $row->update(['refunded' => $total, 'status' => $total >= $row->amount ? 'refunded' : 'partial_refund']);
            if ($row->purpose === 'subscription' && $row->status === 'refunded' && $row->purpose_ref) app(SubscriptionService::class)->cancel((int) $row->purpose_ref);
            if ($row->status === 'refunded' && $row->purpose_ref) {
                if ($row->purpose === 'ticket') app(\App\Services\TicketService::class)->release((int) $row->purpose_ref, 'refunded');
                if ($row->purpose === 'ad') app(\App\Services\AdService::class)->refunded((int) $row->purpose_ref);
                if ($row->purpose === 'jobpack') app(\App\Services\CareerService::class)->refunded((int) $row->purpose_ref);
                if ($row->purpose === 'campaign') app(\App\Services\GivingService::class)->refunded((int) $row->purpose_ref);
                if ($row->purpose === 'course') app(\App\Services\AcademyService::class)->refunded((int) $row->purpose_ref);
                if ($row->purpose === 'housie') app(\App\Services\HousieService::class)->refunded((int) $row->purpose_ref);
            }
            return $r;
        });
    }

    /**
     * Reconciliation: (1) payments still "created" for >30 min are asked from the gateway - if paid there, they are settled here
     * (webhook missed / app closed); (2) paid payments with a gateway id are flagged reconciled.
     * @return array{settled:int, failed:int, checked:int}
     */
    public function reconcile(int $olderThanMinutes = 30): array
    {
        $out = ['settled' => 0, 'failed' => 0, 'checked' => 0];
        Payment::where('status', 'created')->whereNotNull('gateway_order_id')->where('gateway', '!=', 'manual')
            ->where('created_at', '<', now()->subMinutes($olderThanMinutes))->orderBy('id')->limit(200)->get()->each(function (Payment $p) use (&$out) {
                $out['checked']++;
                try {
                    $s = $this->gateway($p->gateway)->fetchStatus($p);
                    if ($s['paid']) { $this->markPaid($p, $s['payment_id'], true); $out['settled']++; }
                    elseif ($p->created_at->lt(now()->subDay())) { $this->markFailed($p); $out['failed']++; }
                } catch (\Throwable $e) { report($e); }
            });
        Payment::where('status', 'paid')->where('reconciled', false)->whereNotNull('gateway_payment_id')->where('gateway', '!=', 'manual')->update(['reconciled' => true]);
        return $out;
    }
}
