<?php

namespace App\Services\Payments;

use App\Models\Payment;

/** Cash / bank transfer / UPI-to-account: an admin marks it paid. */
class ManualGateway implements Gateway
{
    public function name(): string { return 'manual'; }
    public function configured(): bool { return true; }
    public function createOrder(Payment $p, array $opts = []): array { return ['order_id' => 'MAN-' . $p->number, 'instructions' => 'Offline payment: admin confirms after receipt.']; }
    public function verify(array $payload): ?string { return null; }
    public function fetchStatus(Payment $p): array { return ['paid' => $p->status === 'paid', 'payment_id' => $p->gateway_payment_id]; }
    public function refund(Payment $p, float $amount, string $reason = ''): ?string { return null; }
}
