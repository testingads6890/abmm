<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class RazorpayGateway implements Gateway
{
    public function name(): string { return 'razorpay'; }
    public function configured(): bool { return (bool) (config('abmm.razorpay.key_id') && config('abmm.razorpay.key_secret')); }

    private function http() { return Http::withBasicAuth(config('abmm.razorpay.key_id'), config('abmm.razorpay.key_secret'))->acceptJson(); }

    public function createOrder(Payment $p, array $opts = []): array
    {
        $res = $this->http()->post('https://api.razorpay.com/v1/orders', [
            'amount' => (int) round($p->amount * 100), 'currency' => $p->currency, 'receipt' => $p->number,
            'notes' => ['payment_no' => $p->number, 'purpose' => $p->purpose],
        ])->throw()->json();
        return ['order_id' => $res['id'], 'key' => config('abmm.razorpay.key_id'), 'amount' => $res['amount'], 'currency' => $res['currency']];
    }

    public function verify(array $payload): ?string
    {
        $o = (string) ($payload['razorpay_order_id'] ?? ''); $pid = (string) ($payload['razorpay_payment_id'] ?? ''); $sig = (string) ($payload['razorpay_signature'] ?? '');
        if ($o === '' || $pid === '' || $sig === '') return null;
        return hash_equals(hash_hmac('sha256', $o . '|' . $pid, (string) config('abmm.razorpay.key_secret')), $sig) ? $pid : null;
    }

    public function fetchStatus(Payment $p): array
    {
        if (! $p->gateway_order_id) return ['paid' => false, 'payment_id' => null];
        $items = $this->http()->get("https://api.razorpay.com/v1/orders/{$p->gateway_order_id}/payments")->throw()->json('items', []);
        foreach ($items as $i) if (($i['status'] ?? '') === 'captured') return ['paid' => true, 'payment_id' => $i['id']];
        return ['paid' => false, 'payment_id' => null];
    }

    public function refund(Payment $p, float $amount, string $reason = ''): ?string
    {
        abort_unless($p->gateway_payment_id, 422, 'Gateway payment id missing.');
        return $this->http()->post("https://api.razorpay.com/v1/payments/{$p->gateway_payment_id}/refund", [
            'amount' => (int) round($amount * 100), 'notes' => ['reason' => substr($reason, 0, 250)],
        ])->throw()->json('id');
    }
}
