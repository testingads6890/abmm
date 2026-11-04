<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout (hosted page) — works for cards in USD/GBP/CAD/AUD/AED... Amounts in the smallest unit. */
class StripeGateway implements Gateway
{
    private const ZERO_DECIMAL = ['JPY', 'KRW', 'VND'];

    public function name(): string { return 'stripe'; }
    public function configured(): bool { return (bool) config('abmm.stripe.secret_key'); }

    private function http() { return Http::withToken((string) config('abmm.stripe.secret_key'))->asForm()->acceptJson(); }
    private function minor(Payment $p): int { return (int) round($p->amount * (in_array($p->currency, self::ZERO_DECIMAL, true) ? 1 : 100)); }

    public function createOrder(Payment $p, array $opts = []): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $res = $this->http()->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $opts['success_url'] ?? "$base/pay/done?p={$p->number}",
            'cancel_url' => $opts['cancel_url'] ?? "$base/pay/cancel?p={$p->number}",
            'client_reference_id' => $p->number,
            'customer_email' => $p->payer_email ?: null,
            'line_items' => [['quantity' => 1, 'price_data' => [
                'currency' => strtolower($p->currency), 'unit_amount' => $this->minor($p),
                'product_data' => ['name' => $opts['title'] ?? ('ABMM ' . ucfirst($p->purpose))]]]],
            'metadata' => ['payment_no' => $p->number],
        ])->throw()->json();
        return ['order_id' => $res['id'], 'checkout_url' => $res['url']];
    }

    /** Stripe is confirmed by webhook (checkout.session.completed); a client-side payload alone is never trusted. */
    public function verify(array $payload): ?string { return null; }

    /** Webhook signature check (Stripe-Signature: t=...,v1=...). */
    public function verifyWebhook(string $raw, string $header): bool
    {
        $secret = (string) config('abmm.stripe.webhook_secret');
        if ($secret === '' || $header === '') return false;
        parse_str(str_replace(',', '&', $header), $parts);
        $t = $parts['t'] ?? null; $v1 = $parts['v1'] ?? null;
        if (! $t || ! $v1 || abs(time() - (int) $t) > 600) return false;
        return hash_equals(hash_hmac('sha256', $t . '.' . $raw, $secret), $v1);
    }

    public function fetchStatus(Payment $p): array
    {
        if (! $p->gateway_order_id) return ['paid' => false, 'payment_id' => null];
        $s = Http::withToken((string) config('abmm.stripe.secret_key'))->acceptJson()->get("https://api.stripe.com/v1/checkout/sessions/{$p->gateway_order_id}")->throw()->json();
        return ['paid' => ($s['payment_status'] ?? '') === 'paid', 'payment_id' => $s['payment_intent'] ?? null];
    }

    public function refund(Payment $p, float $amount, string $reason = ''): ?string
    {
        abort_unless($p->gateway_payment_id, 422, 'Gateway payment id missing.');
        $minor = (int) round($amount * (in_array($p->currency, self::ZERO_DECIMAL, true) ? 1 : 100));
        return $this->http()->post('https://api.stripe.com/v1/refunds', ['payment_intent' => $p->gateway_payment_id, 'amount' => $minor, 'metadata' => ['reason' => substr($reason, 0, 250)]])->throw()->json('id');
    }
}
