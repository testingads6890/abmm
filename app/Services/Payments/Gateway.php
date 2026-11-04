<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface Gateway
{
    public function name(): string;
    public function configured(): bool;
    /** Create the remote order / checkout. Returns data the app needs: ['order_id'=>..., 'checkout_url'=>?, 'key'=>?]. */
    public function createOrder(Payment $p, array $opts = []): array;
    /** Returns the gateway payment id when the callback payload is genuine, else null. */
    public function verify(array $payload): ?string;
    /** Remote status of an order: ['paid'=>bool,'payment_id'=>?string]. Used by reconciliation. */
    public function fetchStatus(Payment $p): array;
    /** Returns gateway refund id (or null for manual). Throws on gateway error. */
    public function refund(Payment $p, float $amount, string $reason = ''): ?string;
}
